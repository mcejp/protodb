<?php declare(strict_types=1);
/*
 * Copyright (C) 2016-2023 Martin Cejp
 *
 * This file is part of ProtoDB.
 *
 * ProtoDB is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * ProtoDB is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ProtoDB.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace candb\service\drc;

use candb\DB;
use candb\DBHelpers;
use candb\model\DrcIncident;

use candb\service\EnumTypesService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\SubprocessException;
use PDO;
use ReflectionException;

final class DrcService
{
    private $pdo;
    private $dbhelpers;
    private $enum_types;
    private $messages;
    private $packages;
    private $units;

    const violation_descriptions = [
        'BadCharactersInDescription' => 'Description contains invalid or problematic characters',
        'BusUnspecified' => 'Associated bus is not specified &mdash; object will not be exported to DBC file',
        'BusInvalid' => 'Invalid Associated Bus',
        'CanIdConflict' => 'Conflicting CAN ID',
        'DescriptionMissing' => 'Description is missing',
        'MessageFieldTooSmall' => 'Field is too small for the chosen data type',
        'MessageFieldUnitUnspecified' => 'Unit of field value has not been specified',
        'MessageTimeoutZero' => 'Message timeout is set to an invalid value of 0 ms',
        'MessageTxPeriodZero' => 'Message sending period is set to an invalid value of 0 ms',
        'NameNotValidIdentifier' => 'Name is not a valid identifier',
    ];

    public function __construct(DB $db,
                                DBHelpers $dbhelpers,
                                EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        $this->pdo = $db->getPdo();
        $this->dbhelpers = $dbhelpers;
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    public function incidents_by_filter($filter, $values): array
    {
        $query = $this->pdo->prepare("SELECT drc_incident.*, package.name package_name, bus.name bus_name, node.name unit_name, message.name message_name, message2.name message2_name, message_field.name message_field_name " .
            "FROM drc_incident " .
            "LEFT JOIN package ON package.id = drc_incident.package_id " .
            "LEFT JOIN bus ON bus.id = drc_incident.bus_id " .
            "LEFT JOIN node ON node.id = drc_incident.node_id " .
            "LEFT JOIN message ON message.id = drc_incident.message_id " .
            "LEFT JOIN message AS message2 ON message2.id = drc_incident.message2_id " .
            "LEFT JOIN message_field ON message_field.id = drc_incident.message_field_id " .
            "WHERE " . $filter . " " .
            "ORDER BY drc_incident.severity DESC, drc_incident.package_id ASC, drc_incident.bus_id ASC, " .
            "drc_incident.node_id ASC, drc_incident.message_id ASC, drc_incident.message2_id ASC, drc_incident.message_field_id ASC, drc_incident.violation_type ASC");
        $query->execute($values);
        $incidents = $this->dbhelpers->fetch_object_array('\candb\model\DrcIncident', $query, true);

        foreach ($incidents as $incident) {
            $incident->description = self::violation_descriptions[$incident->violation_type] ?? $incident->violation_type;
        }

        return $incidents;
    }

    public function incident_counts_by_filter($filter, $filter_values): array
    {
        $query = $this->pdo->prepare("SELECT severity, COUNT(*) " .
            "FROM drc_incident " .
            "WHERE " . $filter . " " .
            "GROUP by severity " .
            "ORDER BY severity ASC");
        $query->execute($filter_values);

        $incident_counts = [
            DrcIncident::CRITICAL => 0,
            DrcIncident::ERROR => 0,
            DrcIncident::WARNING => 0,
        ];

        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$severity, $count]) {
            $incident_counts[$severity] = (int) $count;
        }

        return $incident_counts;
    }

    public function all_active_incidents(): array
    {
        return $this->incidents_by_filter("drc_incident.valid = '1'", []);
    }

    public function incident_by_id($incident_id): ?DrcIncident
    {
        $incidents = $this->incidents_by_filter('drc_incident.id = ?', [$incident_id]);

        return $incidents ? $incidents[0] : null;
    }

    public function incidents_by_package($package_id): array
    {
        return $this->incidents_by_filter("drc_incident.package_id = ? AND drc_incident.valid = '1'", [$package_id]);
    }

    public function incidents_by_unit($unit_id): array
    {
        return $this->incidents_by_filter("drc_incident.node_id = ? AND drc_incident.valid = '1'", [$unit_id]);
    }

    public function incidents_by_message(int $message_id): array
    {
        return $this->incidents_by_filter("(drc_incident.message_id = ? OR drc_incident.message2_id = ?) AND drc_incident.valid = '1'", [$message_id, $message_id]);
    }

    public function incident_counts_by_message(int $message_id): array
    {
        return $this->incident_counts_by_filter("(drc_incident.message_id = ? OR drc_incident.message2_id = ?) AND ".
            "drc_incident.valid = '1'", [$message_id, $message_id]);
    }

    public function incident_counts_by_node(int $node_id): array
    {
        return $this->incident_counts_by_filter("drc_incident.node_id = ? AND drc_incident.valid = '1'", [$node_id]);
    }

    public function incident_counts_by_package(int $package_id): array
    {
        return $this->incident_counts_by_filter("drc_incident.package_id = ? AND drc_incident.valid = '1'", [$package_id]);
    }

    /**
     * @param int $package_id
     * @return array Numbers of incidents by severity
     * @throws ReflectionException
     * @throws SubprocessException
     * @throws EntityNotFoundException
     */
    public function run_for_package(int $package_id): array
    {
        // Unset all incidents for package
        $query = $this->pdo->prepare("UPDATE drc_incident SET valid = '0' WHERE package_id = ?");
        $query->execute([$package_id]);

        $package = $this->packages->byId($package_id, true) or die("Invalid package_id");

        $service = new ProtodbDrcService($this->pdo, $this->dbhelpers, $this->enum_types, $this->messages, $this->packages, $this->units);
        $incidents = $service->run_for_package($package);

        return $this->update_incidents($incidents);
    }

    public function run_for_unit(int $unit_id): array
    {
        // Unset all incidents for unit
        $query = $this->pdo->prepare("UPDATE drc_incident SET valid = '0' WHERE node_id = ?");
        $query->execute([$unit_id]);

        // Check message fields
        $unit = $this->units->byId($unit_id, true, true, true) or die("Invalid unit_id");
        $package = $this->packages->byId($unit->package_id);

        $service = new ProtodbDrcService($this->pdo, $this->dbhelpers, $this->enum_types, $this->messages, $this->packages, $this->units);
        $incidents = $service->run_for_package($package);

        $incidents = array_filter($incidents, function (DrcIncident $incident) use ($unit_id) {
            return $incident->node_id == $unit_id;
        });

        return $this->update_incidents($incidents);
    }

    public function update_incident(DrcIncident $incident): int
    {
        if ($incident->id === NULL) {
            $query = $this->pdo->prepare('SELECT id FROM drc_incident WHERE ' .
                'violation_type = ? AND ' .
                '(package_id = ? OR (package_id IS NULL AND ? IS NULL)) AND ' .
                '(bus_id = ? OR (bus_id IS NULL AND ? IS NULL)) AND ' .
                '(node_id = ? OR (node_id IS NULL AND ? IS NULL)) AND ' .
                '(message_id = ? OR (message_id IS NULL AND ? IS NULL)) AND ' .
                '(message2_id = ? OR (message2_id IS NULL AND ? IS NULL)) AND ' .
                '(message_field_id = ? OR (message_field_id IS NULL AND ? IS NULL)) AND ' .
                'severity = ?'
            );
            $query->execute([$incident->violation_type,
                             $incident->package_id, $incident->package_id,
                             $incident->bus_id, $incident->bus_id,
                             $incident->node_id, $incident->node_id,
                             $incident->message_id, $incident->message_id,
                             $incident->message2_id, $incident->message2_id,
                             $incident->message_field_id, $incident->message_field_id,
                             $incident->severity]);

            $id = $query->fetchColumn();

            if ($id === false)
                $id = null;
            else
                $id = (int)$id;
        }
        else
            $id = $incident->id;

        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO drc_incident (violation_type, package_id, bus_id, node_id, ' .
                'message_id, message2_id, message_field_id, severity, when_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $query->execute([$incident->violation_type, $incident->package_id, $incident->bus_id, $incident->node_id,
                $incident->message_id, $incident->message2_id, $incident->message_field_id, $incident->severity]);
            $id = (int)$this->pdo->lastInsertId();
        }
        else {
            $query = $this->pdo->prepare("UPDATE drc_incident SET severity = ?, when_updated = CURRENT_TIMESTAMP, valid = '1' WHERE id = ?");
            $query->execute([$incident->severity, $id]);
        }

        return $id;
    }

    /**
     * @param DrcIncident[] $incidents
     * @return array Number of incidents by severity
     */
    private function update_incidents(array $incidents): array
    {
        $num_incidents = [DrcIncident::INFO => 0, DrcIncident::WARNING => 0, DrcIncident::ERROR => 0, DrcIncident::CRITICAL => 0];

        foreach ($incidents as $incident) {
            $this->update_incident($incident);
            $num_incidents[$incident->severity]++;
        }

        return $num_incidents;
    }
}
