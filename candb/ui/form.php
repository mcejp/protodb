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

class Form {
    public $name, $editing, $fields, $submit_url, $data;

    private string $submit_field_name;

    private static $injectedFormData = [];

    public function __construct($name, $editing) {
        $this->name = $name;
        $this->editing = $editing;
        $this->fields = [];

        $this->html_id = "form_" . $name;
        $this->submit_url = "";
        $this->submit_field_name = $name . '__SUBMITTED';
    }

    public function add_field($field) {
        $this->fields[$field->name] = $field;
    }

    public function begin_form() {
        echo "<form id='{$this->html_id}' action='". htmlentities($this->submit_url, ENT_QUOTES) .
                "' method='post' class='form-horizontal'  data-toggle='validator'>";
    }

    public function end_form() {
        echo "</form>";
    }

    public function getFieldByName(string $name): FormField { return $this->fields[$name]; }

    public static function injectFormData(string $formName, array $formData)
    {
        self::$injectedFormData[$formName] = $formData;
    }

    public function init_js() {
        foreach ($this->fields as $field) {
            $field->init_js($this);
        }
    }

    public function get_submitted_data(): ?array {
        if (isset(self::$injectedFormData[$this->name])) {
            $injected = self::$injectedFormData[$this->name];
            unset(self::$injectedFormData[$this->name]);
        }
        else {
            // Has this form been submitted?
            // This rules both non-submit requests (e.g. GET), as well as submission of an unrelated form
            if (filter_input(INPUT_POST, $this->submit_field_name) === null) {
                return null;
            }

            $injected = null;
        }

        $data = [];

        foreach ($this->fields as $field) {
            if ($injected !== null) {
                if (array_key_exists($field->name, $injected)) {
                    $value = $injected[$field->name];
                }
                else {
                    // Default to null (instead of raising an 'Undefined index' exception)
                    // to mimic filter_input behavior
                    $value = null;
                }
            }
            else {
                $value = filter_input(INPUT_POST, $this->name . $field->name);
            }

            if ($value === null)
                throw new \Exception("Missing required field '" . $field->name . "'");

            $data[$field->name] = $field->translate_form_data($value);

            $field->postprocess_submitted_data($this, $injected, $data);
        }

        return $data;
    }

    public function render_edit_toggle($object) {
        if ($this->editing)
            $url = $object->url();
        else
            $url = $object->editing_url();

        echo '<a href="' . htmlentities($url ?: '', ENT_QUOTES) . '">';

        if (!$this->editing)
            echo "<span class='glyphicon glyphicon-pencil'></span>&ensp;Edit";
        else
            echo "<span class='glyphicon glyphicon-file'></span>&ensp;View";

        echo '</a>';
    }

    public function render_field(string $field_name) {
        $field = $this->fields[$field_name];
        return $field->render2($this, $this->name . $field_name, $this->data ? $this->data[$field_name] : null);
    }

    public function set_data($data) {
        $this->data = $data;

        foreach ($this->fields as $name => $field) {
            $field->validate_value($data[$name]);
        }
    }

    public function submit_button($text) {
        if ($this->editing) {
            echo "<button type='submit' class='btn btn-block btn-lg btn-success' name='" .
                htmlentities($this->submit_field_name, ENT_QUOTES) . "'>" .
                $text . "</button>";
        }
    }
}
