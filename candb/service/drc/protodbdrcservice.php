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

use candb\DBHelpers;
use candb\model\DrcIncident;
use candb\model\Package;
use candb\service\EnumTypesService;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\PythonInvoker;
use candb\service\UnitsService;
use candb\SubprocessException;

final class ProtodbDrcService
{
    private $pdo;
    private $dbhelpers;
    private $enum_types;
    private $messages;
    private $packages;
    private $units;

    public function __construct(\PDO $pdo,
                                DBHelpers $dbhelpers,
                                EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        $this->pdo = $pdo;
        $this->dbhelpers = $dbhelpers;
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    /**
     * @param Package $package
     * @return DrcIncident[]
     * @throws SubprocessException
     * @throws \Exception
     */
    public function run_for_package(Package $package): array
    {
        $invoker = new PythonInvoker();
        $result = $invoker->call(['-mprotodb.drc.all_checks', '--scope=package=' . $package->name],
                                                     null, ['PROTODB_CONN_STRING' => $this->build_dsn_for_protodb()]
                                                    );

        if ($result->status !== 0) {
            // TODO: Refactor as PythonInvoker::check_call
            throw new SubprocessException($result->status, $result->stdout, $result->stderr);
        }

        return $this->parse_output($result->stdout);
    }

    private function build_dsn_for_protodb()
    {
        return getenv("PROTODB_PDO_DSN") . ';user=' . getenv("PROTODB_PDO_USER") . ';password=' . getenv("PROTODB_PDO_PASSWORD") .
                ';collation=utf8mb4_unicode_ci';
    }

    /**
     * @param string $stdout
     * @return DrcIncident[]
     * @throws \Exception
     */
    private function parse_output(string $stdout): array
    {
        $incidents = [];

        // ProtoDB outputs rows specifying violations such as:
        //
        // BusUnspecified message=Message(TestPackage1.ECU1.Message1)
        // DescriptionMissing object=Message(TestPackage2.ECU2.Message2)
        //
        // We have to go row by row and parse out the object references
        //
        // NOTE: This was a dumb idea and has lead to repeated bugs with
        //       (lack of) escaping of characters in the output.
        //       Should just have used JSON.
        foreach (explode("\n", $stdout) as $row) {
            if (!$row) {
                continue;
            }

            $tokens = str_getcsv($row, ' ');
            $violation_type = $tokens[0];

            $package_id = null;
            $bus_id = null;
            $unit_id = null;
            $message_id = null;
            $message2_id = null;
            $message_field_id = null;
            $details = null;

            foreach (array_slice($tokens, 1) as $attribute) {
                [$key, $value] = explode('=', $attribute);

                $matches = [];

                if (preg_match('/^Bus\(([^)]+)\)$/', $value, $matches)) {
                    [$package_name, $bus_name] = explode('.', $matches[1]);

                    $query = $this->pdo->prepare("SELECT package.id AS package_id, bus.id AS bus_id " .
                        "FROM bus " .
                        "LEFT JOIN package ON package.id = bus.package_id " .
                        "WHERE package.name = ? AND bus.name = ?");
                    $query->execute([$package_name, $bus_name]);
                    [$package_id, $bus_id] = $query->fetch();
                    assert($package_id !== null);
                    assert($bus_id !== null);
                    $package_id = (int)$package_id;
                    $bus_id = (int)$bus_id;
                }
                else if (preg_match('/^Message\(([^)]+)\)$/', $value, $matches)) {
                    [$package_name, $unit_name, $message_name] = explode('.', $matches[1]);

                    $query = $this->pdo->prepare("SELECT package.id AS package_id, node.id AS unit_id, message.id AS message_id " .
                        "FROM message " .
                        "LEFT JOIN node ON node.id = message.node_id " .
                        "LEFT JOIN package ON package.id = node.package_id " .
                        "WHERE package.name = ? AND node.name = ? AND message.name = ? AND node.valid = 1 AND message.valid = 1");
                    $query->execute([$package_name, $unit_name, $message_name]);
                    [$package_id, $unit_id, $message_id_new] = $query->fetch();
                    assert($package_id !== null);
                    assert($unit_id !== null);
                    $package_id = (int)$package_id;
                    $unit_id = (int)$unit_id;

                    if ($message_id === null) {
                        $message_id = (int)$message_id_new;
                    }
                    else {
                        $message2_id = (int)$message_id_new;
                    }
                }
                else if (preg_match('/^MessageField\(([^)]+)\)$/', $value, $matches)) {
                    [$package_name, $unit_name, $message_name, $field_name] = explode('.', $matches[1]);

                    $query = $this->pdo->prepare("SELECT package.id AS package_id, node.id AS unit_id, message.id AS message_id, message_field.id AS field_id " .
                        "FROM message_field " .
                        "LEFT JOIN message ON message.id = message_field.message_id " .
                        "LEFT JOIN node ON node.id = message.node_id " .
                        "LEFT JOIN package ON package.id = node.package_id " .
                        "WHERE package.name = ? AND node.name = ? AND message.name = ? AND message_field.name = ? " .
                        "AND node.valid = 1 AND message.valid = 1 AND message_field.valid = 1");
                    $query->execute([$package_name, $unit_name, $message_name, $field_name]);
                    [$package_id, $unit_id, $message_id, $message_field_id] = $query->fetch();
                    assert($package_id !== null);
                    assert($unit_id !== null);
                    assert($message_id !== null);
                    assert($message_field_id !== null);
                    $package_id = (int)$package_id;
                    $unit_id = (int)$unit_id;
                    $message_id = (int)$message_id;
                    $message_field_id = (int)$message_field_id;
                }
                else if (preg_match('/^Unit\(([^)]+)\)$/', $value, $matches)) {
                    [$package_name, $unit_name] = explode('.', $matches[1]);

                    $query = $this->pdo->prepare("SELECT package.id AS package_id, node.id AS unit_id " .
                        "FROM node " .
                        "LEFT JOIN package ON package.id = node.package_id " .
                        "WHERE package.name = ? AND node.name = ? AND node.valid = 1");
                    $query->execute([$package_name, $unit_name]);
                    [$package_id, $unit_id] = $query->fetch();
                    assert($package_id !== null);
                    assert($unit_id !== null);
                    $package_id = (int)$package_id;
                    $unit_id = (int)$unit_id;
                }
                else {
                    throw new \Exception("Unhandled object " . $value);
                }
            }

            // Severity is precomputed in DB to facilitate filtering
            // TODO: better solution needed
            $violation_type_to_severity = [
                'BadCharactersInDescription' =>     DrcIncident::ERROR,
                'BusInvalid' =>                     DrcIncident::ERROR,
                'BusUnspecified' =>                 DrcIncident::WARNING,
                'CanIdConflict' =>                  DrcIncident::ERROR,
                'DescriptionMissing' =>             DrcIncident::WARNING,
                'MessageFieldTooSmall' =>           DrcIncident::ERROR,
                'MessageFieldUnitUnspecified' =>    DrcIncident::WARNING,
                'MessageTimeoutZero' =>             DrcIncident::ERROR,
                'MessageTxPeriodZero' =>            DrcIncident::ERROR,
                'NameNotValidIdentifier' =>         DrcIncident::ERROR,
            ];

            $severity = $violation_type_to_severity[$violation_type] ?? DrcIncident::INFO;

            $incidents[] = new DrcIncident(null, $violation_type, $package_id, $bus_id, $unit_id, $message_id, $message2_id,
                $message_field_id, $severity, $details);
        }

        return $incidents;
    }
}
