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

namespace candb\ui;

class GUICommon
{
    private static $popovers = [
        'message_field_unit' => "For example:\n&ndash; V, rad, m/s., m/s^2\n&ndash; %, raw. Leave empty if N/A",
        'message_field_factor' => "Size of 1 LSB.\n\nSignal value is calculated as {Offset + Raw_Value * Step} {Unit}",
        'message_field_offset' => "Signal offset.\n\nSignal value is calculated as {Offset + Raw_Value * Step} {Unit}",
        'message_field_min' => "Minimum value of signal, in <strong>physical units</strong>.",
        'message_field_max' => "Maximum value of signal, in <strong>physical units</strong>.",
    ];

    public static function drc_button(object $entity, int $num_errors, int $num_warnings): void {
        if ($num_errors > 0) {
            echo '<li class="bg-danger">';
        }
        else if ($num_errors > 0 || $num_warnings > 0) {
            echo '<li class="bg-warning">';
        }
        else {
            echo '<li>';
        }
        echo '<a href="' . htmlentities($entity->urlDrc(), ENT_QUOTES) . '"><span class="glyphicon glyphicon-ok-sign"></span>&ensp;QC';
        if ($num_errors > 0) {
            echo '&ensp;<span class="text-danger glyphicon glyphicon-remove" title="Error"></span>&nbsp;' . $num_errors;
        }
        if ($num_warnings > 0) {
            echo '&ensp;<span class="text-warning glyphicon glyphicon-alert" title="Warning"></span>&nbsp;' . $num_warnings;
        }
        echo '</a></li>';
    }

    public static function popover($id) {
        echo '<a tabindex="0" role="button" data-toggle="popover" data-trigger="focus" title="" data-content="';
        echo nl2br(htmlentities(static::$popovers[$id], ENT_QUOTES));
        echo '" data-html="true"><span class="glyphicon glyphicon-question-sign"></span></a>';
    }
}
