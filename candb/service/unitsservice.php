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

namespace candb\service;

use candb\DB;
use candb\DBHelpers;
use candb\model\Changelog;
use candb\model\Unit;
use candb\service\exception\EntityNotFoundException;

final class UnitsService
{
    const OPERATION_SENDER = 'SENDER';
    const OPERATION_RECEIVER = 'RECEIVER';

    private $pdo;
    private $dbhelpers;
    /** @var ChangelogService */ private $log;

    public function __construct(DB $db, DBHelpers $dbhelpers, ChangelogService $log) {
        $this->pdo = $db->getPdo();
        $this->dbhelpers = $dbhelpers;
        $this->log = $log;
    }

    public function allUnitNames() {
        $query = $this->pdo->prepare("SELECT CONCAT(package.name, '/', node.name) name, node.id FROM node " .
                "LEFT JOIN package ON package.id = node.package_id WHERE node.valid = 1 ORDER BY name ASC");
        $query->execute();
        $bus_names = $query->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP, 1);
        return array_map(fn ($row) => $row[0], $bus_names);
    }

    /**
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     */
    public function byId(int $unit_id, bool $with_buses = false, bool $with_messages = false, bool $with_enums = false): Unit {
        $query = $this->pdo->prepare("SELECT * FROM node WHERE node.id = ? and node.valid = '1'");

        $query->execute([$unit_id]) or die();

        $unit = $this->dbhelpers->fetch_object('\candb\model\Unit', $query);

        if ($unit === null)
            throw new EntityNotFoundException("Invalid unit ID");

        if ($with_buses) {
            $query = $this->pdo->prepare("SELECT node_bus.id id, node_bus.node_id node_id, " .
                "node_bus.note, bus.id bus_id, bus.name bus_name, bus.bitrate bus_bitrate " .
                "FROM node_bus, bus " .
                "WHERE node_bus.node_id = ? AND bus.id = node_bus.bus_id");

            $query->execute([$unit_id]) or die();
            $unit->bus_links = $this->dbhelpers->fetch_object_array('candb\model\BusLink', $query, true);
        }

        if ($with_messages) {
            $query = $this->pdo->prepare("SELECT message.*, node.name owner_name FROM message " .
                "LEFT JOIN node ON node.id = message.node_id " .
                "WHERE message.node_id = ? AND message.valid = '1' ORDER BY message.can_id ASC");

            $query->execute([$unit_id]) or die();
            $unit->messages = $this->dbhelpers->fetch_object_array('\candb\model\Message', $query, true);

            $query = $this->pdo->prepare("SELECT message_node.id link_id, message_node.operation, message.*, " .
                "node.name owner_name, package.id package_id, package.name package_name ".
                "FROM message_node " .
                "LEFT JOIN message ON message.id = message_node.message_id " .
                "LEFT JOIN node ON node.id = message.node_id " .
                "LEFT JOIN package ON package.id = node.package_id " .
                "WHERE message_node.node_id = ? ORDER BY message.can_id ASC");

            $query->execute([$unit_id]) or die();
            $messages = $this->dbhelpers->fetch_object_array('\candb\model\Message', $query, true);
            $unit->sent_messages = [];
            $unit->received_messages = [];

            foreach ($messages as $message) {
                if ($message->operation == 'SENDER')
                    $unit->sent_messages[] = ['id' => (int)$message->link_id, 'message_id' => $message->id, 'message' => $message, 'operation' => $message->operation, 'node_id' => $unit_id];
                else
                    $unit->received_messages[] = ['id' => (int)$message->link_id, 'message_id' => $message->id, 'message' => $message, 'operation' => $message->operation, 'node_id' => $unit_id];
            }
        }

        if ($with_enums) {
            $query = $this->pdo->prepare("SELECT * FROM enum_type " .
                "WHERE enum_type.node_id = ? ORDER BY enum_type.name ASC");

            $query->execute([$unit_id]) or die();
            $unit->enum_types = $this->dbhelpers->fetch_object_array('\candb\model\EnumType', $query);
        }

        return $unit;
    }

    /**
     * @return Unit[]
     */
    public function by_bus_id(int $bus_id): array
    {
        $query = $this->pdo->prepare("SELECT node.* FROM node_bus " .
                                     "LEFT JOIN node ON node.id = node_bus.node_id " .
                                     "WHERE node_bus.bus_id = ? and node.valid = '1'");

        $query->execute([$bus_id]) or die();
        $units = $this->dbhelpers->fetch_object_array('\candb\model\Unit', $query);

        return $units;
    }

    // Lacks proper testcase!
    public function delete_forever(Unit $unit): void
    {
        $this->delete_forever_by_id($unit->id);
    }

    // Lacks proper testcase!
    public function delete_forever_by_id(int $unit_id): void
    {
        // message is ON DELETE CASCADE
        // node_bus is ON DELETE CASCADE
        // unit_sdo is ON DELETE RESTRICT

        // Enum types are fully cascaded
        $query = $this->pdo->prepare('DELETE FROM enum_type WHERE node_id = ?');
        $query->execute([$unit_id]);

        $query = $this->pdo->prepare('DELETE FROM node WHERE id = ?');
        $query->execute([$unit_id]);
    }

    public function insert(Unit $unit, string $who_changed): int {
        $this->pdo->beginTransaction();
        $query = $this->pdo->prepare("INSERT INTO node (package_id, name, description, authors_hw, " .
                "authors_sw, code_model_version, who_changed)" .
                " VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$query->execute([$unit->package_id, $unit->name, $unit->description,
                $unit->authors_hw, $unit->authors_sw, $unit->code_model_version, $who_changed])) {
            $this->pdo->rollBack();
            throw new Exception($query->errorInfo());
        }

        $unit_id = (int)$this->pdo->lastInsertId();
        $this->log->log_insert(Changelog::TABLE_UNIT, $unit_id, $who_changed);

        $this->pdo->commit();
        return $unit_id;
    }

    public function insert_message_link(int $unit_id, int $message_id, string $operation)
    {
        $this->dbhelpers->insert('message_node', ['node_id' => $unit_id, 'message_id' => $message_id, 'operation' => $operation]);
    }

    public function move_to_package(int $unit_id, int $package_id, string $who_changed): void {
        $this->pdo->beginTransaction();

        $this->dbhelpers->update_by_id('node', $unit_id, [
            'package_id' => $package_id,
            'who_changed' => $who_changed,
        ]);

        $this->log->log_update(Changelog::TABLE_UNIT, $unit_id, $who_changed);

        $this->pdo->commit();
    }

    public function update(Unit $unit, string $who_changed): void
    {
        $this->dbhelpers->update_by_id('node', $unit->id, [
            'name' => $unit->name,
            'description' => $unit->description,
            'code_model_version' => $unit->code_model_version,
            'authors_hw' => $unit->authors_hw,
            'authors_sw' => $unit->authors_sw,
            'who_changed' => $who_changed,
        ]);

        $this->log->log_update(Changelog::TABLE_UNIT, $unit->id, $who_changed);
    }

    public function updateBusLinks(int $unit_id, array $bus_links): void {
        $data = array_map(function($bus_link) {
            return ['id' => $bus_link->id, 'bus_id' => $bus_link->bus_id, 'note' => $bus_link->note];
        }, $bus_links);

        $this->dbhelpers->update_list('node_bus', 'node_id', $unit_id,
                                       'id', ['bus_id', 'note'], $data);
    }

    // TODO: awful interface. should take a strongly-typed array
    public function updateMessageLinks(int $unit_id, array $sent_messages, array $received_messages): void
    {
        // TODO: perhaps this is not the right place to do the de-duplication

        $data = [];

        foreach ($sent_messages as $link) {
            if (array_filter($data, function($entry) use ($link) {
                return $entry['message_id'] === $link['message_id'] && $entry['operation'] === 'SENDER';
            })) {
                // do not insert duplicate entries
                continue;
            }

            $data[] = ['id' => $link['id'], 'message_id' => $link['message_id'], 'operation' => 'SENDER'];
        }

        foreach ($received_messages as $link) {
            if (array_filter($data, function($entry) use ($link) {
                return $entry['message_id'] === $link['message_id'] && $entry['operation'] === 'RECEIVER';
            })) {
                // do not insert duplicate entries
                continue;
            }

            $data[] = ['id' => $link['id'], 'message_id' => $link['message_id'], 'operation' => 'RECEIVER'];
        }

        $this->dbhelpers->update_list('message_node', 'node_id', $unit_id,
                                      'id', ['message_id', 'operation'], $data);
    }
}

