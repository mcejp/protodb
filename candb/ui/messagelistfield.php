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

use candb\service\PackagesService;

final class MessageListField extends TableField {
    public function __construct($name, PackagesService $packages, ?int $preferred_package_id, ?int $preferred_unit_id) {
        $packages = $packages->get_all(with_nodes: true, with_messages: true);

        $all_groups = [];

        foreach ($packages as $package) {
            $groups = [];

            foreach ($package->units as $unit) {
                if (!count($unit->messages)) {
                    continue;
                }

                $group_name = $package->name . ' / ' . $unit->name;
                $message_list = [];

                foreach ($unit->messages as $message) {
                    if (!$message->name) {
                        continue;
                    }

                    $message_list[] = [$message->id, $group_name . ' / ' . $message->name];
                }

                if ($unit->id === $preferred_unit_id) {
                    // If this is the preferred unit, put group at the beginning of the array
                    // (key-value arrays in PHP are strictly ordered)
                    $groups = [$group_name => $message_list] + $groups;
                }
                else {
                    $groups[$group_name] = $message_list;
                }
            }

            if ($package->id === $preferred_package_id) {
                // If this is the preferred package, put it at the beginning of the array
                $all_groups = $groups + $all_groups;
            }
            else {
                $all_groups = $all_groups + $groups;
            }
        }

        parent::__construct($name, [
            ['name' => 'message_id', 'title' => 'Message', 'field' => EnumField::with_option_groups(null, $all_groups)]
        ], false);
    }
}
