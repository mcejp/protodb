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
use candb\service\exception\EntityNotFoundException;

final class BusService {
    private $pdo;

    /** @var DBHelpers */
    private $db_helpers;

    public function __construct(DB $db, DBHelpers $db_helpers) {
        $this->pdo = $db->getPdo();
        $this->db_helpers = $db_helpers;
    }

    /**
     * @return string[] Mapping of bus ID -> formatted bus name (including package name and bit rate)
     */
    public function all_bus_names(): array {
        $query = $this->pdo->prepare("SELECT bus.id, bus.name, bus.bitrate, package.name package_name FROM bus LEFT JOIN package ON bus.package_id = package.id");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);

        $bus_names = [];
        foreach ($rows as $row)
            $bus_names[$row['id']] = $row['package_name'] . ' - ' . $row['name'] . ' (' . ($row['bitrate'] / 1000) . ' kbit/s)';

        return $bus_names;
    }

    /**
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function by_id(int $bus_id): Bus {
        $buses = $this->get_by_ids([$bus_id]);
        return $buses[$bus_id];
    }

    /**
     * @return Bus[]
     * @throws \ReflectionException
     */
    public function by_unit_id(int $unit_id): array {
        $query = $this->pdo->prepare("SELECT bus.*, bus.package_id package_id FROM node_bus LEFT JOIN bus ON node_bus.bus_id = bus.id WHERE node_bus.node_id = ?");

        $query->execute([$unit_id]) or die();
        /** @var Bus[] $buses */ $buses = $this->db_helpers->fetch_object_array('\candb\model\Bus', $query);

        return $buses;
    }


    /**
     * @return Bus[] associative array mapping ID -> Bus
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function get_by_ids(array $bus_ids): array {
        if (!count($bus_ids)) {
            return [];
        }

        $ids_string = $this->db_helpers->make_quoted_list($bus_ids);

        $query = $this->pdo->prepare('SELECT bus.* FROM bus WHERE bus.id IN (' . $ids_string .')');

        $query->execute();
        $buses = $this->db_helpers->fetch_object_array('\candb\model\Bus', $query, true, 'id');

        if (count($buses) != count($bus_ids)) {
            throw new EntityNotFoundException("Invalid bus ID");
        }

        return $buses;
    }
}
