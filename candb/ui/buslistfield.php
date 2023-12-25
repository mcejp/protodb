<?php declare(strict_types=1);
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

final class BusListField extends TableField {
    public function __construct(?string $name, array $option_groups) {
        parent::__construct($name, [
            ['name' => 'bus_id', 'title' => 'Bus', 'field' => EnumField::with_option_groups(null, $option_groups)]
        ], ordered: false, placeholder_html: TableField::PLACEHOLDER_MDASH, show_table_heading: false);
    }

    /**
     * @param $form
     * @param $name
     * @param array|null $data
     * @return void
     */
    public function render2($form, $name, $data): void {
        $mapped_data = array_map(fn($row) => (object) ['bus_id' => $row], $data);
        $this->render2_internal($form, $name, $mapped_data);
    }

    public function translate_form_data(string $submitted_value): array {
        $rows = json_decode($submitted_value, true);
        $bus_ids = [];

        if (!is_array($rows)) {
            throw new ValidationException("Submitted data for TableField must be an array");
        }

        foreach ($rows as $row) {
            if (!is_int($row["bus_id"])) {
                throw new ValidationException("int expected");
            }

            $bus_ids[] = $row["bus_id"];
        }

        return $bus_ids;
    }
}
