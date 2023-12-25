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

class MessageField
{
    const TYPE_RESERVED = 'reserved';

    public $id, $message_id, $position, $name, $description, $type, $bit_size,
            $array_length, $unit, $factor, $offset, $min, $max;
    public $ranges;
    public $start_bit = -1;

    // TODO: $id, $message_id can be null only for incomplete (not yet saved) entities. Maybe this is not the best way to handle it.
    public function __construct(?int $id, ?int $message_id, int $position, string $name, string $description,
            string $type, int $bit_size, int $array_length,
            ?string $unit, ?string $factor, ?string $offset, ?string $min, ?string $max) {
        $this->id = $id;
        $this->message_id = $message_id;
        $this->position = $position;
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->bit_size = $bit_size;
        $this->array_length = $array_length;
        $this->unit = $unit;
        $this->factor = $factor;
        $this->offset = $offset;
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * @return bool if the field is of an enumeration type. The ID of the enum is then `(int)$this->type`
     */
    public function is_enum(): bool {
        // FIXME: awful
        return !in_array($this->type, ['bool', 'uint', 'int', 'float', 'multiplex', self::TYPE_RESERVED], true);
    }

    public function is_reserved(): bool {
        return $this->type === self::TYPE_RESERVED || $this->name === '';
    }
}
