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

final class Package {
    public $id, $name;

    /** @var Bus[] $buses */
    public $buses = [];

    /** @var Unit[] $units */
    public $units = [];

    public function __construct(?int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function url() { return static::s_url($this->id); }
    public function url_export($format) { return $GLOBALS['base_path'] . "packages/{$this->id}/export-" . $format; }
    public function urlCommunicationMatrix() { return $GLOBALS['base_path'] . "bootstrap.php?controller=PackageController&target=view_communication_matrix&package_id={$this->id}"; }
    public function urlDrc() { return $GLOBALS['base_path'] . "packages/{$this->id}/drc"; }
    public function title() { return $this->name; }

    public static function s_url($package_id) { return $GLOBALS['base_path'] . "packages/{$package_id}"; }
}
