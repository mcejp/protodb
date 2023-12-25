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

class Bus {
    public $id, $package_id, $dbc_id, $name, $bitrate;

    public function __construct(?int $id, int $package_id, ?int $dbc_id, string $name, int $bitrate)
    {
        $this->id = $id;
        $this->package_id = $package_id;
        $this->dbc_id = $dbc_id;
        $this->name = $name;
        $this->bitrate = $bitrate;
    }

    public function format_bitrate(): string {
        if ($this->bitrate >= 1000000) {
            return ($this->bitrate/1000000) . ' Mbit/s';
        }
        else if ($this->bitrate >= 1000) {
            return ($this->bitrate/1000) . ' kbit/s';
        }
        else {
            return $this->bitrate . ' bit/s';
        }
    }

    public function url() { return static::s_url($this->id); }
    public static function s_url(int $bus_id) { return $GLOBALS['base_path'] . "buses/{$bus_id}"; }
    public function title() { return $this->name; }
}
