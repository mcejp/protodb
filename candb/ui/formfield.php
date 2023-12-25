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

abstract class FormField {
    public ?string $name;

    public function init_js($form) {
    }

    public function postprocess_submitted_data($form, $injected, &$data) {
    }

    abstract public function render2(Form $form, string $html_field_name, mixed $value): void;

    /**
     * Translate submitted form data into native type
     * @param mixed $submitted_value
     * @return mixed
     * @throws ValidationException
     */
    abstract public function translate_form_data(string $submitted_value): mixed;

    /**
     * Translate JSON data into native type
     * @param mixed $submitted_value
     * @return mixed
     * @throws ValidationException
     */
    abstract public function translate_json_data(mixed $submitted_value): mixed;

    /**
     * @param $message
     * @param $value
     * @throws ValidationException
     */
    public function raise_validation_exception($message, $value)
    {
        $message .= ' (';
        if ($this->name) {
            $message .= 'field name ' . $this->name . ', ';
        }

        $message .= 'value ' . var_export($value, true) . ')';

        throw new ValidationException($message);
    }

    /**
     * Validate JSON value.
     * @param mixed $value
     * @return void
     * @throws ValidationException
     */
    abstract public function validate_value(mixed $value): void;
}
