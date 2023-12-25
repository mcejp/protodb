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

namespace candb\model;

class EnumType {
    public $id, $node_id, $name, $description, $who_changed, $when_changed;

    /**
     * @var EnumItem[]
     */
    public $items;

    public function __construct(?int $id, int $node_id, string $name, string $description,
            ?string $who_changed = null, ?string $when_changed = null)
    {
        $this->id = $id;
        $this->node_id = $node_id;
        $this->name = $name;
        $this->description = $description;
        $this->who_changed = $who_changed;
        $this->when_changed = $when_changed;
    }

    public function url() { return $this->id ? static::s_url($this->id) : NULL; }
    public function url_delete() { return $GLOBALS['base_path'] . "enum-types/{$this->id}/delete"; }
    public function editing_url() { return $this->id ? static::s_url($this->id).'/edit' : NULL; }
    public function title() { return $this->name ? $this->name : "New enum"; }

    public static function s_url($enum_type_id) { return $GLOBALS['base_path'] . "enum-types/{$enum_type_id}"; }
    public static function s_new_url($unit_id) { return $GLOBALS['base_path'] . "enum-types/new-{$unit_id}"; }
}
