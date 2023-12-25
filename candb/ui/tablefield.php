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

class TableField extends FormField {
    private $var_name;

    /* @var $columns FormField[] */

    const PLACEHOLDER_NO_DATA = '<div class="text-center">No data.</div>';
    const PLACEHOLDER_MDASH = '&mdash;';

    public function __construct(public ?string $name,
                                public array $columns,
                                public bool $ordered,
                                private string $js_class_name = 'FormTable',
                                private string $placeholder_html = self::PLACEHOLDER_NO_DATA,
                                private bool $show_table_heading = true) {
    }

    public function get_column_by_name($name) {
        foreach ($this->columns as $col) {
            if ($col['name'] === $name) {
                return $col;
            }
        }
    }

    public function getVarName(): string { return $this->var_name; }

    public function init_js($form) {
        if (!$form->editing)
            return;

        echo "var columns = [";
        foreach ($this->columns as $column) {
            $column_json = ['name' => $column['name'], 'type' => $column['field']->type];

            if (method_exists($column['field'], 'get_min_value'))
                $column_json['min'] = $column['field']->get_min_value();

            if (method_exists($column['field'], 'get_max_length'))
                $column_json['maxLength'] = $column['field']->get_max_length();

            if (method_exists($column['field'], 'isRequired'))
                $column_json['required'] = $column['field']->isRequired();

            if ($column['field']->type == 'enum') {
                $column_json['option_groups'] = [];

                foreach ($column['field']->option_groups as $label => $options) {
                    $column_json['option_groups'][] = ['label' => $label, 'options' => array_values($options)];
                }

                $column_json['default'] = $column['field']->default;
            }

            echo json_encode($column_json) . ",";
        }
        echo "];\n";

        $this->var_name = $form->name . $this->name;

        echo $this->var_name . " = new {$this->js_class_name}($('#" . $form->name . $this->name . "'), columns, " . (int)$this->ordered . ");\n";
        echo "$('#form_{$form->name}').submit(form_table_update.bind(null, $('#" . $form->name . $this->name . "'), columns));\n";
    }

    /**
     * @param $form
     * @param $name
     * @param array|null $data
     * @return void
     */
    public function render2($form, $name, $data): void {
        $this->render2_internal($form, $name, $data);
    }

    protected function render2_internal($form, $name, ?array $data) {
        $num_columns = count($this->columns) + ($form->editing ? 1 : 0);

        echo "<table class='table table-condensed' id='" . $name . "'>";

        if ($this->show_table_heading) {
            echo "<thead><tr>";

            foreach ($this->columns as $column) {
                echo "<th>" . htmlentities($column['title'], ENT_QUOTES);
                if (isset($column['popover_id']))
                    GUICommon::popover($column['popover_id']);
                echo "</th>";
            }

            echo "</tr></thead>";
        }

        echo "<tbody>";

        if (!$form->editing) {
            foreach ($data as $id => $row) {
                echo "<tr>";
                foreach ($this->columns as $column) {
                    echo "<td>";
                    $column_name = $column['name'];
                    $column['field']->render2($form, $name . '_' . $column_name . '_' . $id, $data[$id]->{$column_name});
                    echo "</td>";
                }
                echo "</tr>";
            }
        }

        if ($form->editing) {
            echo "<tr><td colspan='$num_columns' class='text-center text-muted'>";
            echo "<div><button type='button' class='btn btn-sm btn-condensed btn-default add-row'><span class='glyphicon glyphicon-plus-sign'></span>&ensp;Add row</button></div>";
            echo "</td></tr>";
        }

        if (!$form->editing && count($data) === 0) {
            echo "<tr><td colspan='$num_columns' class='text-muted'>";
            echo $this->placeholder_html;
            echo "</td></tr>";
        }

        echo "</tbody>";
        echo "</table>";

        $json = $data ? json_encode($data) : '[]';
        echo "<input type='hidden' name='" . $name . "' value='" . htmlentities($json, ENT_QUOTES) . "'>";
    }

    public function translate_form_data(string $submitted_value): array {
        $rows = json_decode($submitted_value, true);
        $new_rows = [];

        if (!is_array($rows)) {
            throw new ValidationException("Submitted data for TableField must be an array");
        }

        // Validate each row
        foreach ($rows as $row) {
            // Validate each column
            $new_row = [];

            // TODO: do this better
            if (array_key_exists('id', $row)) {
                $new_row['id'] = (int)$row['id'];
            }

            foreach ($this->columns as $col) {
                $new_row[$col['name']] = $col['field']->translate_json_data($row[$col['name']]);
            }
            $new_rows[] = $new_row;
        }

        return $new_rows;
    }

    public function translate_json_data(mixed $submitted_value): mixed
    {
        throw new \Exception("Not implemented");
    }

    public function validate_value(mixed $value): void {
        // TODO
    }
}
