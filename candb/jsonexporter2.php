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

namespace candb;

use candb\model\Message;
use candb\service\BusService;
use candb\service\EntityCache;
use candb\service\EnumTypesService;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;

class ExportSet
{
    public $messages = [];
    public $packages = [];
    public $units = [];

    /** @var EntityCache */ private $cache;

    public function __construct(EntityCache $cache)
    {
        $this->cache = $cache;
    }

    public function add_bus_by_id(int $bus_id,
                                  MessagesService $messages_service,
                                  UnitsService $units_service): void {
        // iterate all messages associated to bus
        $all_messages = $messages_service->by_bus_id($bus_id);

        // add units as necessary
        foreach ($all_messages as $message) {
            $this->add_message($message, $units_service);
        }

        // however, also consider all ECUs linked to this bus and add all of their message that do not have an Associated Bus specified
        // this way, accessory devices can be connected to several different car generations
        // (the ECUs are linked to buses from all the cars, but the messages are left un-associated)
        $relevant_units = $units_service->by_bus_id($bus_id);

        foreach ($relevant_units as $unit) {
            $unit = $this->cache->get_unit_by_id($unit->id, $units_service);

            foreach ($unit->messages as $message) {
                $message = $this->cache->get_message_by_id($message->id, $messages_service);
                
                if ($message->bus_id === null) {
                    $this->add_message($message, $units_service);
                }
            }
        }
    }

    private function add_message(Message $message,
                                 UnitsService $units_service): void {
        if (array_key_exists($message->id, $this->messages)) {
            return;
        }

        // ensure we have the corresponding ECU as well

        $unit_id = $message->node_id;
        $this->add_unit_with_enums($unit_id, $units_service);

        $this->messages[$message->id] = true;
    }

    private function add_package_by_id(int $package_id): void {
        if (array_key_exists($package_id, $this->packages)) {
            return;
        }

        $this->packages[$package_id] = true;
    }

    private function add_unit_with_enums(int $unit_id,
                                         UnitsService $units_service): void {
        if (array_key_exists($unit_id, $this->units)) {
            return;
        }

        $unit = $this->cache->get_unit_by_id($unit_id, $units_service);
        $this->add_package_by_id($unit->package_id);

        $this->units[$unit_id] = true;
    }

    public function get_entity_cache(): EntityCache
    {
        return $this->cache;
    }
}

class JsonExporter2
{
    /** @var BusService */ private $bus_service;
    /** @var EnumTypesService */ private $enum_types_service;
    /** @var MessagesService */ private $messages_service;
    /** @var PackagesService */ private $packages_service;
    /** @var UnitsService */ private $units_service;

    private const reserved_type = 'reserved';

    public function __construct(BusService $bus_service,
                                EnumTypesService $enum_types_service,
                                MessagesService $messages_service,
                                PackagesService $packages_service,
                                UnitsService $units_service)
    {
        $this->bus_service = $bus_service;
        $this->enum_types_service = $enum_types_service;
        $this->messages_service = $messages_service;
        $this->packages_service = $packages_service;
        $this->units_service = $units_service;
    }

    public function export_buses_of_package(int $package_id, EntityCache $entity_cache): array
    {
        $export_set = new ExportSet($entity_cache);

        $package = $entity_cache->get_package_by_id($package_id, $this->packages_service);

        foreach ($package->buses as $bus) {
            $export_set->add_bus_by_id($bus->id, $this->messages_service, $this->units_service);
        }

        return $this->render_set($export_set);
    }

    private function get_absolute_bus_name(int $bus_id, EntityCache $cache): string
    {
        $bus = $cache->get_bus_by_id($bus_id, $this->bus_service);
        $package = $cache->get_package_by_id($bus->package_id, $this->packages_service);

        return $package->name . "." . $bus->name;
    }

    private function get_model_for_message(Message $message, EntityCache $cache): array
    {
        if ($message->bus_id !== null) {
            $bus_name = $this->get_absolute_bus_name($message->bus_id, $cache);
        }
        else {
            $bus_name = null;
        }

        assert($message->timeout === null || is_int($message->timeout));
        assert($message->tx_period === null || is_int($message->tx_period));

        $message->layout();

        $message_model = [
            'name' => $message->name,
            'description' => $message->description !== '' ? $message->description : null,
            'bus' => $bus_name,
            'fields' => [],
            'frame_type' => $message->get_frame_type(),
            'id' => $message->get_can_id(),
            'length' => $message->num_bytes,
            'received_by' => [],
            'sent_by' => [],
            'timeout' => $message->timeout,
            'tx_period' => $message->tx_period,
        ];

        foreach ($message->fields as $field) {
            if ($field->is_reserved()) {
                $name = null;
                $type = self::reserved_type;
            }
            else if ($field->is_enum()) {
                $enum_type = $cache->get_enum_type_by_id((int)$field->type, $this->enum_types_service);
                $unit = $cache->get_unit_by_id($enum_type->node_id, $this->units_service);

                $unit_name = $unit->name;

                $name = $field->name;
                $type = 'enum ' . $unit_name . '_' . $enum_type->name;
            }
            else {
                $name = $field->name;
                $type = $field->type;
            }

            $regex = '/([0-9.]+)\/([0-9.]+)/';
            if (is_numeric($field->factor)) {
                $factor_num = (float) $field->factor;
            }
            else if (preg_match($regex, $field->factor ?: '', $matches)) {
                $factor_num = (float) $matches[1] / (float)$matches[2];
            }
            else {
                $factor_num = null;
            }

            $message_model['fields'][] = [
                'name' => $name,
                'description' => $field->description !== '' ? $field->description : null,
                'type' => $type,
                'bits' => (int)$field->bit_size,
                'count' => (int)$field->array_length,
                'start_bit' => (int)$field->start_bit,
                'unit' => $field->unit !== '' ? $field->unit : null,
                'factor' => $field->factor !== '' ? $field->factor : null,
                'factor_num' => $factor_num,
                'offset' => $field->offset !== '' ? $field->offset : null,
                'min' => $field->min !== '' ? $field->min : null,
                'max' => $field->max !== '' ? $field->max : null,
            ];
        }

        $sent_by = $this->messages_service->getAllUnitsWithOperation($message, 'SENDER');
        $received_by = $this->messages_service->getAllUnitsWithOperation($message, 'RECEIVER');

        foreach ($sent_by as $unit) {
            $message_model['sent_by'][] = $unit->package_name . '.' . $unit->name;
        }

        foreach ($received_by as $unit) {
            $message_model['received_by'][] = $unit->package_name . '.' . $unit->name;
        }

        return $message_model;
    }

    private function render_set(ExportSet $set): array
    {
        $model = [
            'version' => 2,
            'packages' => [],
        ];

        $cache = $set->get_entity_cache();

        foreach (array_keys($set->packages) as $package_id) {
            $package = $cache->get_package_by_id($package_id, $this->packages_service);

            $package_model = [
                'name' => $package->name,
                'buses' => [],
                'units' => [],
            ];

            foreach ($package->buses as $bus) {
                $bus_model = [
                    'dbc_id' => $bus->dbc_id,
                    'name' => $bus->name,
                    'bitrate' => $bus->bitrate,
                ];

                $package_model['buses'][] = $bus_model;
            }

            foreach ($package->units as $unit) {
                if (!array_key_exists($unit->id, $set->units)) {
                    continue;
                }

                $unit = $cache->get_unit_by_id($unit->id, $this->units_service);

                $unit_model = [
                    'name' => $unit->name,
                    'description' => $unit->description !== '' ? $unit->description : null,
                    'bus_links' => [],
                    'enum_types' => [],
                    'messages' => [],
                ];

                foreach ($unit->bus_links as $bus_link) {
                    $bus_link_model = [
                        'bus' => $this->get_absolute_bus_name($bus_link->bus_id, $cache),
                        'note' => $bus_link->note !== '' ? $bus_link->note : null,
                    ];

                    $unit_model['bus_links'][] = $bus_link_model;
                }

                foreach ($unit->enum_types as $enum_type) {
                    $enum_type = $cache->get_enum_type_by_id($enum_type->id, $this->enum_types_service);

                    $enum_type_model = [
                        'name' => $enum_type->name,
                        'description' => $enum_type->description !== '' ? $enum_type->description : null,
                        'items' => [],
                    ];

                    foreach ($enum_type->items as $item) {
                        $enum_type_model['items'][] = [
                            'name' => $item->name,
                            'value' => $item->value,
                            'description' => $item->description !== '' ? $item->description : null
                        ];
                    }

                    // Sort enum items by value
                    usort($enum_type_model['items'], fn($it1, $it2) => $it1["value"] <=> $it2["value"]);

                    $unit_model['enum_types'][] = $enum_type_model;
                }

                // Sort enum types by name
                usort($unit_model['enum_types'], fn($e1, $e2) => $e1["name"] <=> $e2["name"]);

                foreach ($unit->messages as $message) {
                    if (!array_key_exists($message->id, $set->messages)) {
                        continue;
                    }

                    $message = $cache->get_message_by_id($message->id, $this->messages_service);

                    $unit_model['messages'][] = $this->get_model_for_message($message, $cache);
                }

                // Sort messages by name
                usort($unit_model['messages'], fn($m1, $m2) => $m1["name"] <=> $m2["name"]);

                $package_model['units'][] = $unit_model;
            }

            $model['packages'][] = $package_model;
        }

        // Sort packages by name
        usort($model['packages'], fn($p1, $p2) => $p1["name"] <=> $p2["name"]);

        return $model;
    }
}