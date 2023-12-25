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
use candb\model\Package;
use candb\service\exception\EntityNotFoundException;

final class PackagesService {
    private $pdo;

    /** @var DBHelpers */
    private $db_helpers;

    public function __construct(DB $db, DBHelpers $db_helpers) {
        $this->pdo = $db->getPdo();
        $this->db_helpers = $db_helpers;
    }

    public function allPackageNames(): array {
        $query = $this->pdo->prepare("SELECT package.name, package.id FROM package");
        $query->execute();
        $names = $query->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_GROUP, 1);
        return array_map(fn ($row) => $row[0], $names);
    }

    /**
     * Search package by matching column (should be unique i.e. `id` or `name`)
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    private function by_column(string $column, string $value, $with_units, $with_unit_stats, $with_buses): Package {
        $query = $this->pdo->prepare("SELECT * FROM package WHERE package.$column = ?");

        $query->execute([$value]) or die();
        /** @var Package $pkg */ $pkg = $this->db_helpers->fetch_object('\candb\model\Package', $query);

        if ($pkg === null)
            throw new EntityNotFoundException("Invalid package ID");

        if ($with_buses) {
            $query = $this->pdo->prepare("SELECT bus.*, bus.package_id, bus.bitrate " .
                    "FROM bus WHERE bus.package_id = ?");
            $query->execute([$pkg->id]) or die();
            $pkg->buses = $this->db_helpers->fetch_object_array('\candb\model\Bus', $query,
                    true);      // what extra properties are we fetching here?
        }

        if ($with_units) {
            if (!$with_unit_stats) {
                $query = $this->pdo->prepare("SELECT node.* FROM node " .
                    "WHERE node.package_id = ? AND node.valid = 1 ORDER BY node.name ASC");
            }
            else {
                $query = $this->pdo->prepare("SELECT node.*, COUNT(DISTINCT message_id) num_messages FROM node ".
                    "LEFT JOIN message_node ON message_node.node_id = node.id WHERE node.package_id = ? " .
                    "AND node.valid = 1 GROUP BY node.id ORDER BY node.name ASC");
            }

            $query->execute([$pkg->id]) or die();
            $units = $this->db_helpers->fetch_object_array('\candb\model\Unit', $query, true);

            foreach ($units as $unit) {
                $unit->package_id = $pkg->id;
                $pkg->units[] = $unit;
            }
        }

        return $pkg;
    }

    /**
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function byId(int $package_id, $with_units = false, $with_unit_stats = false, $with_buses = false): Package
    {
        return $this->by_column('id', (string) $package_id, $with_units, $with_unit_stats, $with_buses);
    }

    /**
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function by_name(string $name, $with_units = false, $with_unit_stats = false, $with_buses = false): Package
    {
        return $this->by_column('name', $name, $with_units, $with_unit_stats, $with_buses);
    }

    /**
     * @return Package[]
     * @throws \ReflectionException
     */
    public function get_all(bool $with_buses = false, bool $with_nodes = false, bool $with_messages = false): array
    {
        $statement = $this->pdo->query("SELECT * FROM package");
        /** @var Package[] */
        $packages = $this->db_helpers->fetch_object_array('\candb\model\Package', $statement, false, 'id');

        if ($with_buses) {
            $this->db_helpers->fetch_children($packages,
                "SELECT bus.* FROM bus",
                '\candb\model\Bus',
                "package_id",
                "buses"
            );
        }
        else {
            foreach ($packages as $package) {
                $package->buses = null;
            }
        }

        if ($with_nodes) {
            $nodes = $this->db_helpers->fetch_children($packages,
                "SELECT node.* FROM node WHERE node.valid = 1",
                '\candb\model\Unit',
                "package_id",
                "units"
            );

            if ($with_messages) {
                $this->db_helpers->fetch_children($nodes,
                    "SELECT message.* FROM message WHERE message.valid = 1",
                    '\candb\model\Message',
                    "node_id",
                    "messages"
                );
            }
            else {
                foreach ($nodes as $node) {
                    $node->messages = null;
                }
            }
        }
        else {
            if ($with_messages) {
                throw new \InvalidArgumentException("with_messages requires with_nodes");
            }

            foreach ($packages as $package) {
                $package->units = null;
            }
        }

        return $packages;
    }

    public function insert(Package $package, string $who_changed): int
    {
        $this->db_helpers->insert('package', [
            'id' => $package->id,
            'name' => $package->name,
            'who_changed' => $who_changed,
        ]);
        return $package->id;
    }
}
