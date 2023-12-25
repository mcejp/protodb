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

use candb\model\Bus;
use candb\model\EnumType;
use candb\model\Message;
use candb\model\Package;
use candb\model\Unit;

class EntityCache
{
    /** @var Bus[] */ private $buses = [];
    /** @var EnumType[] */ private $enum_types = [];
    /** @var Message[] */ private $messages = [];
    /** @var Package[] */ private $packages = [];
    /** @var Unit[] */ private $units = [];

    public function get_bus_by_id(int $bus_id, BusService $bus_service): Bus {
        if (array_key_exists($bus_id, $this->buses)) {
            return $this->buses[$bus_id];
        }

        $bus = $bus_service->by_id($bus_id);
        $this->buses[$bus_id] = $bus;
        return $bus;
    }

    public function get_enum_type_by_id(int $enum_type_id, EnumTypesService $enum_types)
    {
        if (array_key_exists($enum_type_id, $this->enum_types)) {
            return $this->enum_types[$enum_type_id];
        }

        $enum_type = $enum_types->byId($enum_type_id);
        $this->enum_types[$enum_type_id] = $enum_type;

        return $enum_type;
    }

    public function get_message_by_id(int $message_id, MessagesService $messages_service): Message {
        if (array_key_exists($message_id, $this->messages)) {
            return $this->messages[$message_id];
        }

        $message = $messages_service->byId($message_id, true);
        $this->messages[$message_id] = $message;
        return $message;
    }

    public function get_package_by_id(int $package_id, PackagesService $packages_service): Package {
        if (array_key_exists($package_id, $this->packages)) {
            return $this->packages[$package_id];
        }

        $package = $packages_service->byId($package_id, true, false, true);
        $this->packages[$package_id] = $package;
        return $package;
    }

    public function get_unit_by_id(int $unit_id, UnitsService $units_service): Unit {
        if (array_key_exists($unit_id, $this->units)) {
            return $this->units[$unit_id];
        }

        $unit = $units_service->byId($unit_id, true, true, true);
        $this->units[$unit_id] = $unit;

        return $unit;
    }
}
