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
use candb\model\EnumItem;
use candb\model\EnumType;
use candb\service\exception\EntityNotFoundException;

class EnumTypesService
{
    private $pdo;
    private $dbhelpers;

    public function __construct(DB $db, DBHelpers $dbhelpers)
    {
        $this->pdo = $db->getPdo();
        $this->dbhelpers = $dbhelpers;
    }

    /**
     * @param $enum_type_id
     * @return EnumType
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     */
    public function byId($enum_type_id): EnumType
    {
        $query = $this->pdo->prepare("SELECT * FROM enum_type WHERE enum_type.id = ?");

        $query->execute([$enum_type_id]) or die();
        /** @var EnumType $enum_type */ $enum_type = $this->dbhelpers->fetch_object('\candb\model\EnumType', $query);

        if ($enum_type === null)
            throw new EntityNotFoundException("Invalid enum ID");

        if (true) {
            $query = $this->pdo->prepare("SELECT * FROM enum_item WHERE enum_item.enum_type_id = ? ORDER BY enum_item.position ASC");

            $query->execute([$enum_type_id]) or die();
            $enum_type->items = $this->dbhelpers->fetch_object_array('candb\model\EnumItem', $query);
        }

        return $enum_type;
    }

    // Lacks proper testcase!
    public function delete_forever_by_id(int $enum_type_id): void
    {
        $query = $this->pdo->prepare('DELETE FROM enum_type WHERE id = ?');
        $query->execute([$enum_type_id]);
    }

    public function insert(EnumType $enum_type, string $who_changed): int
    {
        return $this->dbhelpers->insert('enum_type', [
            'node_id' => $enum_type->node_id,
            'name' => $enum_type->name,
            'description' => $enum_type->description,
            'who_changed' => $who_changed,
        ]);
    }

    public function update(EnumType $enum_type, string $who_changed): void
    {
        $this->dbhelpers->update_by_id('enum_type', $enum_type->id, [
            'name' => $enum_type->name,
            'description' => $enum_type->description,
            'who_changed' => $who_changed,
        ]);
    }

    /**
     * @param int $enum_type_id
     * @param EnumItem[] $items
     */
    public function updateItems(int $enum_type_id, array $items): void
    {
        $data = array_map(function($item, $index) {
            return (array)$item;
        }, $items, array_keys($items));

        $this->dbhelpers->update_list('enum_item', 'enum_type_id', $enum_type_id, 'id', ['position', 'name', 'description', 'value'], $data);
    }
}
