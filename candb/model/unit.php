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

class Unit {
    public $id, $package_id, $name, $description, $code_model_version, $canopen_node_id,
            $authors_hw, $authors_sw, $advanced_options, $who_changed, $when_changed;
    public $who_locked, $when_locked, $why_locked;

    /** @var BusLink[] */ public $bus_links;
    public $enum_types;
    /** @var ?Message[] */ public array|null $messages;
    public $sent_messages, $received_messages;

    // TODO: $code_model_version SHOULD NOT default to 1. It does, because:
    //  - we don't want to make it nullable (for simplicity)
    //  - it is useful to have a default for when we don't care (unit tests)
    public function __construct(?int $id, int $package_id, string $name, string $description,
            ?string $canopen_node_id = null, string $authors_hw = '', string $authors_sw = '',
            int $code_model_version = 1, ?string $advanced_options = null,
            ?string $who_changed = null, ?string $when_changed = null)
    {
        $this->id = $id;
        $this->package_id = $package_id;
        $this->name = $name;
        $this->canopen_node_id = $canopen_node_id;
        $this->description = $description;
        $this->authors_hw = $authors_hw;
        $this->authors_sw = $authors_sw;

        $this->code_model_version = $code_model_version;
        $this->advanced_options = $advanced_options;

        $this->who_changed = $who_changed;
        $this->when_changed = $when_changed;

        $this->messages = [];
    }

    public function url() { return $this->id ? static::s_url($this->id) : NULL; }
    public function editing_url() { return $this->id ? static::s_url($this->id).'/edit' : NULL; }
    public function url_export($format) { return $this->id ? static::s_url($this->id)."/export-{$format}" : NULL; }
    public function urlDrc() { return $GLOBALS['base_path'] . "units/{$this->id}/drc"; }
    public function title() { return $this->name ? $this->name : "New unit"; }

    public static function s_url($unit_id) { return $GLOBALS['base_path'] . "units/{$unit_id}"; }
    public static function s_new_url($packages_id) { return $GLOBALS['base_path'] . "units/new-{$packages_id}"; }

    public function get_bus_ids(): array
    {
        return array_map(fn(BusLink $bus_link) => $bus_link->bus_id, $this->bus_links);
    }
}
