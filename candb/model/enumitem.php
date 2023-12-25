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

class EnumItem {
    public $id, $enum_type_id, $position, $name, $value, $description;

    // TODO: $id, $enum_type_id can be null only for incomplete (not yet saved) entities. Maybe this is not the best way to handle it.
    public function __construct(?int $id, ?int $enum_type_id, int $position, string $name, int $value, string $description) {
        $this->id = $id;
        $this->enum_type_id = $enum_type_id;
        $this->position = $position;
        $this->name = $name;
        $this->value = $value;
        $this->description = $description;
    }
}
