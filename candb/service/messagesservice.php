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
use candb\model\Bus;
use candb\model\Changelog;
use candb\model\Message;
use candb\model\MessageField;
use candb\service\exception\EntityNotFoundException;

final class MessagesService
{
    private $pdo;
    private $dbhelpers;
    /** @var ChangelogService */ private $log;

    public function __construct(DB $db, DBHelpers $dbhelpers, ChangelogService $log)
    {
        $this->pdo = $db->getPdo();
        $this->dbhelpers = $dbhelpers;
        $this->log = $log;
    }

    public function allMessageNames(): array {
        $query = $this->pdo->prepare("SELECT message.name, message.id FROM message WHERE valid = '1' ORDER BY name ASC");
        $query->execute();
        $bus_names = $query->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP, 1);
        return array_map(fn ($row) => $row[0], $bus_names);
    }

    /**
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function byId(int $message_id, bool $with_fields = false, bool $with_buses = false): Message {
        $messages = $this->get_by_ids([$message_id], with_buses: $with_buses, with_fields: $with_fields);
        return $messages[$message_id];
    }

    /** @return Message[] */
    public function by_bus_id(int $bus_id): array {
        $query = $this->pdo->prepare("SELECT message.*, node.name owner_name FROM message " .
                "LEFT JOIN node ON node.id = message.node_id WHERE message.bus_id = ? AND message.valid = '1'");

        $query->execute([$bus_id]) or die();
        $messages = $this->dbhelpers->fetch_object_array('\candb\model\Message', $query);

        return $messages;
    }

    public function delete(Message $message, string $who_changed): void
    {
        $this->dbhelpers->update_by_id('message', $message->id, [
            'valid' => '0',
            'who_changed' => $who_changed,
        ]);

        // FIXME: Must also delete all message links

        $this->log->log_delete(Changelog::TABLE_MESSAGE, $message->id, $who_changed);
    }

    // Lacks proper testcase!
    public function delete_forever(Message $message): void
    {
        $this->delete_forever_by_id($message->id);
    }

    // Lacks proper testcase!
    public function delete_forever_by_id(int $message_id): void
    {
        // drc_incident is ON DELETE CASCADE
        // message_field is ON DELETE CASCADE
        // message_node is ON DELETE CASCADE

        $query = $this->pdo->prepare('DELETE FROM message WHERE id = ?');
        $query->execute([$message_id]);
    }

    /**
     * @throws \ReflectionException
     * @return Message[]
     */
    public function getAllUnitsWithOperation(Message $message, string $operation): array
    {
        $query = $this->pdo->prepare("SELECT node.*, package.name package_name FROM message_node " .
            "LEFT JOIN node ON node.id = message_node.node_id " .
            "LEFT JOIN package ON package.id = node.package_id " .
            "WHERE message_node.message_id = ? AND message_node.operation = ? " .
            "ORDER BY package_name ASC, name ASC");

        $query->execute([$message->id, $operation]);

        return $this->dbhelpers->fetch_object_array('\candb\model\Unit', $query, true);
    }

    /** @return Message[]
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function get_by_ids(array $message_ids, bool $with_buses, bool $with_fields): array {
        if (!count($message_ids)) {
            return [];
        }

        $message_ids_string = $this->dbhelpers->make_quoted_list($message_ids);

        $query = $this->pdo->prepare('SELECT message.*, node.name owner_name FROM message ' .
                'LEFT JOIN node ON node.id = message.node_id '.
                'WHERE message.id IN (' . $message_ids_string .') AND message.valid = \'1\'');

        $query->execute();
        $messages = $this->dbhelpers->fetch_object_array('\candb\model\Message', $query, true);

        if (count($messages) != count($message_ids)) {
            throw new EntityNotFoundException("Invalid message ID");
        }

        $messages_by_id = [];

        foreach ($messages as $message) {
            $message->fields = [];
            $messages_by_id[$message->id] = $message;
        }

        // fetch additional associated buses
        if ($with_buses) {
            $query = $this->pdo->prepare('SELECT message_id, bus_id FROM message_bus WHERE message_id IN (' . $message_ids_string . ')');
            $query->execute();
            $rows = $query->fetchAll(\PDO::FETCH_OBJ);

            // distribute results by message id
            foreach ($messages_by_id as $message_id => $message) {
                $filtered = array_filter($rows, fn($row) => $row->message_id === $message_id);
                $buses = array_map(fn($row) => (int) $row->bus_id, $filtered);
                $message->set_buses($buses);
            }
        }
        else {
            foreach ($messages_by_id as $message) {
                // make sure get_buses triggers an exception
                $message->set_buses(null);
            }
        }

        if ($with_fields) {
            $query = $this->pdo->prepare('SELECT * FROM message_field WHERE message_id IN (' . $message_ids_string . ') AND valid = 1 ORDER BY position ASC');

            $query->execute();
            $fields = $this->dbhelpers->fetch_object_array('\candb\model\MessageField', $query);

            foreach ($fields as $field) {
                $messages_by_id[$field->message_id]->fields[] = $field;
            }
        }

        return $messages_by_id;
    }

    /** @return int[] */
    public function get_ids_associated_to_bus(int $bus_id): array {
        $query = $this->pdo->prepare("SELECT message.id FROM message WHERE message.bus_id = ? AND message.valid = '1' UNION " .
                                     "SELECT message.id FROM message_bus LEFT JOIN message ON message.id = message_bus.message_id WHERE message_bus.bus_id = ? AND message.valid = '1'");

        $query->execute([$bus_id, $bus_id]) or die();

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function insert(Message $message, string $who_changed): int {
        assert(!isset($message->id));
        $message->validate();

        $query = $this->pdo->prepare("INSERT INTO message (node_id, bus_id, can_id_type, can_id, name, description, tx_period, timeout, who_changed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $query->execute([$message->node_id, $message->bus_id, $message->get_can_id_type(), $message->get_can_id(), $message->name,
                $message->description, $message->tx_period, $message->timeout, $who_changed]);

        $message_id = (int)$this->pdo->lastInsertId();

        $this->log->log_insert(Changelog::TABLE_MESSAGE, $message_id, $who_changed);

        $this->dbhelpers->update_list_unordered_unique_scalars('message_bus', 'message_id', $message_id, 'bus_id', $message->get_buses());

        return $message_id;
    }

    public function update(Message $message, string $who_changed): void
    {
        $this->dbhelpers->update_by_id('message', $message->id, [
            'bus_id' => $message->bus_id,
            'can_id_type' => $message->get_can_id_type(),
            'can_id' => $message->get_can_id(),
            'name' => $message->name,
            'description' => $message->description,
            'tx_period' => $message->tx_period,
            'timeout' => $message->timeout,
            'who_changed' => $who_changed,
        ]);

        $this->log->log_update(Changelog::TABLE_MESSAGE, $message->id, $who_changed);

        $this->dbhelpers->update_list_unordered_unique_scalars('message_bus', 'message_id', $message->id, 'bus_id', $message->get_buses());
    }

    /**
     * @param int $message_id
     * @param MessageField[] $fields
     * @return void
     */
    public function updateFields(int $message_id, array $fields): void
    {
        $data = array_map(function($field) {
            return ['id' => $field->id, 'name' => $field->name, 'description' => $field->description, 'type' => $field->type,
                    'bit_size' => $field->bit_size, 'array_length' => $field->array_length, 'unit' => $field->unit,
                    'factor' => $field->factor, 'offset' => $field->offset, 'min' => $field->min, 'max' => $field->max];
        }, $fields);

        $this->dbhelpers->update_list('message_field', 'message_id', $message_id, 'id',
                ['name', 'description', 'type', 'bit_size', 'array_length', 'unit', 'factor', 'offset', 'min', 'max'],
                $data, 'position');
    }
}
