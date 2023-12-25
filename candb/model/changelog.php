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

class Changelog
{
    public $id, $table, $action, $row, $who_changed, $when_changed;

    const TABLE_MESSAGE = 'message';
    const TABLE_UNIT = 'node';

    const DELETE = 'DELETE';
    const INSERT = 'INSERT';
    const UPDATE = 'UPDATE';

    public function __construct(int $id, string $table, string $action, int $row, string $who_changed, string $when_changed) {
        $this->id = $id;
        $this->table = $table;
        $this->action = $action;
        $this->row = $row;
        $this->who_changed = $who_changed;
        $this->when_changed = $when_changed;
    }
}
