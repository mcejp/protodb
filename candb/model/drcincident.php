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

class DrcIncident
{
    const OFF = 0;
    const INFO = 2;
    const WARNING = 3;
    const ERROR = 4;
    const CRITICAL = 5;

    public $id, $violation_type, $package_id, $bus_id, $node_id, $message_id, $message2_id, $message_field_id, $severity, $when_updated, $valid;

    public $package_name, $bus_name, $unit_name, $message_name, $message2_name, $message_field_name;

    public function __construct(?int $id, string $violation_type, ?int $package_id, ?int $bus_id, ?int $node_id,
                                ?int $message_id, ?int $message2_id, ?int $message_field_id, int $severity,
                                ?string $when_updated = null, bool $valid = true)
    {
        $this->id = $id;
        $this->violation_type = $violation_type;
        $this->package_id = $package_id;
        $this->bus_id = $bus_id;
        $this->node_id = $node_id;
        $this->message_id = $message_id;
        $this->message2_id = $message2_id;
        $this->message_field_id = $message_field_id;
        $this->severity = $severity;
        $this->when_updated = $when_updated;
        $this->valid = $valid;
    }

    public function get_params() { return "id={$this->id} violation_type={$this->violation_type} package_id={$this->package_id} bus_id={$this->bus_id} node_id={$this->node_id} " .
    "message_id={$this->message_id} {$this->message2_id} message_field_id={$this->message_field_id} severity={$this->severity}"; }

    public static function s_url($incident_id) { return $GLOBALS['base_path'] . "drc/incidents/{$incident_id}"; }
}
