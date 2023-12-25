<?php
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

final class DBHelpers {
    private $pdo;

    public function __construct(DB $db)
    {
        $this->pdo = $db->getPdo();
    }

    public static function construct_object_from_assoc(string $class_name, array $assoc, bool $allow_extra_properties): object
    {
        $class = new \ReflectionClass($class_name);
        $constructor = $class->getConstructor();
        assert($constructor !== null);

        $args = [];

        foreach ($constructor->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $assoc)) {
                $value = $assoc[$parameter->getName()];

                $type = $parameter->getType();

                if ($type === NULL) {
                }
                else {
                    if ($type->allowsNull() && $value === null) {
                        // Preserve null
                    }
                    else if ($type->getName() === "DateTime") {
                        // Parse DateTime (or throw an exception)
                        $value = new \DateTime($value);
                    }
                    else if (!settype($value, $type->getName())) {
                        throw new \ReflectionException("Failed to reflect field " . $parameter->getName());
                    }
                }

                $args[] = $value;
            }
            else {
                if ($parameter->isOptional()) {
                    $args[] = $parameter->getDefaultValue();
                    continue;
                }
                else {
                    throw new \ReflectionException("Missing required field " . $parameter->getName());
                }
            }
        }

        $object = $class->newInstanceArgs($args);

        if ($allow_extra_properties) {
            $all_parameters = array_map(fn($parameter) => $parameter->getName(), $constructor->getParameters());

            $extra_keys = array_diff(array_keys($assoc), $all_parameters);

            foreach ($extra_keys as $key)
                $object->{$key} = $assoc[$key];
        }

        return $object;
    }

    public function fetch_children(array $parents,
                                  string $query,
                                  string $class_name,
                                  string $parent_id_property_in_child,
                                  string $children_property_in_parent): array
    {
        $query = $this->pdo->query($query);
        $children = $this->fetch_object_array($class_name, $query);

        // distribute children by parent id
        foreach ($parents as $parent) {
            $parent->$children_property_in_parent = array_filter($children, fn($child) => $child->$parent_id_property_in_child === $parent->id);
        }

        return $children;
    }

    /**
     * @throws \ReflectionException
     */
    public function fetch_object(string $class_name, \PDOStatement $query, bool $allow_extra_properties = false): ?object
    {
        $assoc = $query->fetch(\PDO::FETCH_ASSOC);

        if (!$assoc)
            return null;

        return self::construct_object_from_assoc($class_name, $assoc, $allow_extra_properties);
    }

    /**
     * @param string $class_name Fully-qualified name of the class to unserialize
     * @param \PDOStatement $query
     * @param bool $allow_extra_properties
     * @param string|null $key_name If specified an associative array will be built using this column as key
     * @return array
     * @throws \ReflectionException
     */
    public function fetch_object_array(string $class_name, \PDOStatement $query, bool $allow_extra_properties = false, ?string $key_name = null): array
    {
        $objects = [];

        foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $assoc) {
            $object = self::construct_object_from_assoc($class_name, $assoc, $allow_extra_properties);;

            if ($key_name !== null)
                $objects[$object->{$key_name}] = $object;
            else
                $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert(string $table, array $data): int {
        $columns = array_map(fn($col) => "`$col`", array_keys($data));

        $query = "INSERT INTO `$table` (".implode(", ", $columns).") VALUES (".
            implode(", ", array_fill(0, count($columns), "?")).")";

        $insert = $this->pdo->prepare($query);
        $insert->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function make_quoted_list(array $items): string {
        return implode(", ", array_map(fn($item) => $this->pdo->quote((string)$item), $items));
    }

    /**
     * @param string $table
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_by_id(string $table, int $id, array $data): void {
        $assignments = array_map(fn($col) => "`$col` = ?", array_keys($data));
        $query = "UPDATE `$table` SET ".implode(", ", $assignments)." WHERE `id` = ?";

        $update = $this->pdo->prepare($query);

        $data[] = $id;
        $update->execute(array_values($data));
    }

    /**
     * Update an ordered or unordered list. Executes a database transaction.
     * @param string $table Table name (e.g. enum_item)
     * @param string $owner_id_column Owning entity ID column (e.g. enum_item.enum_type_id)
     * @param int $owner_id Owning entity ID
     * @param string $id_column ID column (e.g. id)
     * @param array $columns List of columns to update
     * @param array $data Array of associative arrays containing the data
     * @param ?string $position_column If not NULL, this column will be set to each row's index in $data.
     */
    public function update_list(string $table, string $owner_id_column, int $owner_id, string $id_column,
            array $columns, array $data, ?string $position_column = NULL): void {
        // TODO: this function has some warts
        // - it rolls its own database transaction -> why?
        // - it might falsely trigger an UNIQUE constraint due to the order of operations --
        //   but this is tricky to get right in any case, as MySQL does not support deferrable constraints and
        //   always evaluates them after every updated row

        // Prepare data
        $updates = [];
        $inserts = [];
        $preserved_ids = [];

        foreach ($data as $i => $row) {
            $values = array_map(fn($col) => $row[$col], $columns);

            if ($position_column) {
                $values[] = $i;
            }

            if (isset($row[$id_column])) {
                // Row ID is set -- update existing row

                $updates[] = array_merge($values, [$row[$id_column]]);
                $preserved_ids[] = $this->pdo->quote($row[$id_column]);
            }
            else {
                // Row ID is empty -- insert new row

                $inserts[] = array_merge([$owner_id], $values);
            }
        }

        // Run the transaction
        $this->pdo->beginTransaction();

        $delete_query = "DELETE FROM `$table` WHERE `$owner_id_column` = ?" . ($preserved_ids ? " AND $id_column NOT IN (" .
                implode(", ", $preserved_ids) . ")" : "");

        $cleanup = $this->pdo->prepare($delete_query);
        $cleanup->execute([$owner_id]);

        if ($position_column) {
            $column_list = array_merge($columns, [$position_column]);
        }
        else {
            $column_list = $columns;
        }

        if (count($updates)) {
            // Prepare generic INSERT & UPDATE queries
            $assignments = array_map(fn($col) => "`$col` = ?", $column_list);
            $update_query = "UPDATE `$table` SET ".implode(", ", $assignments)." WHERE `$id_column` = ?";

            $update = $this->pdo->prepare($update_query);

            foreach ($updates as $update_data) {
                $update->execute($update_data);
            }
        }

        if (count($inserts)) {
            $insert_query = "INSERT INTO `$table` ($owner_id_column, ".implode(", ", $column_list).") VALUES (".
                implode(", ", array_fill(0, 1 + count($column_list), "?")).")";

            $insert = $this->pdo->prepare($insert_query);

            foreach ($inserts as $insert_data) {
                $insert->execute($insert_data);
            }
        }

        $this->pdo->commit();
    }

    /**
     * Update an unordered list of scalar. Does not execute a database transaction.
     * This assumes that the table has a UNIQUE constraint for ($id_column, $value_column)
     *
     * The implementation is sadly MySQL/MariaDB-specific.
     *
     * @param string $table Table name (e.g. enum_item)
     * @param string $owner_id_column Owning entity ID column (e.g. enum_item.enum_type_id)
     * @param int $owner_id Owning entity ID
     * @param string $value_column Name of value column
     * @param array $values List of scalar values
     */
    public function update_list_unordered_unique_scalars(string $table, string $owner_id_column, int $owner_id,
        string $value_column, array $values): void {
        // delete all values except those in $values
        $delete_query = "DELETE FROM `$table` WHERE `$owner_id_column` = ?" . (count($values) > 0 ? " AND $value_column NOT IN (" .
                implode(", ", $values) . ")" : "");
        $cleanup = $this->pdo->prepare($delete_query);
        $cleanup->execute([$owner_id]);

        // now insert all values, ignoring duplicates on DB level
        if (count($values)) {
            $insert_query = "INSERT IGNORE INTO `$table` ($owner_id_column, $value_column) VALUES (?, ?)";
            $insert = $this->pdo->prepare($insert_query);

            foreach ($values as $insert_data) {
                $insert->execute([$owner_id, $insert_data]);
            }
        }
    }
}
