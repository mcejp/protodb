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

use candb\service\PythonInvoker;
use candb\SubprocessException;

class CanIdField extends FormField {
    static ?array $custom_formats = null;

    public function __construct(public ?string $name = null) {
    }

    /**
     * @throws SubprocessException
     */
    private static function load_custom_formats(): array
    {
        // Delegate to helper module to parse YAML & "flatten" the user-provided configuration
        $invoker = new PythonInvoker();
        $result = $invoker->call(["-m", "protodb.tools.frame_id_parser"]);

        if ($result->status !== 0) {
            throw new SubprocessException($result->status, $result->stdout, $result->stderr);
        }

        return json_decode($result->stdout);
    }

    /**
     * @throws SubprocessException
     */
    private static function get_custom_formats(): array
    {
        if (self::$custom_formats === null) {
            $formats_list = self::load_custom_formats();
            $keys = array_map(fn ($format) => $format->name, $formats_list);
            self::$custom_formats = array_combine($keys, $formats_list);
        }

        // Very dirty, we just return the raw YAML model
        return self::$custom_formats;
    }

    /**
     * @throws SubprocessException
     */
    public function init_js($form) {
        $frame_type_defs = [];
        foreach (self::get_custom_formats() as $format) {
            $frame_type_defs[$format->name] = [
                'fields' => array_map(fn ($field) => [
                    'bits' => $field->bits,
                    'lsb' => $field->lsb_pos,
                    'options' => array_map(fn ($label) => $label ?? 'null', $field->flat_labels)
                ], $format->fields)
            ];
        }

        echo 'const frame_type_defs = ' . json_encode($frame_type_defs) . ";\n";
        echo "can_id_init($('#" . $form->name . $this->name . "'), frame_type_defs);\n";
    }

    public function postprocess_submitted_data($form, $injected, &$data) {
        $type_value = ($injected !== null) ? $injected[$this->name . '_type'] : filter_input(
                INPUT_POST, $form->name . $this->name . '_type');
        $data[$this->name . '_type'] = $type_value;
    }

    private static function present_id_html($value, \stdClass $template): string {
        assert($template->frame_type == 'CAN_STD');

        $field_labels = [];

        foreach ($template->fields as $field) {
            $index = ($value >> $field->lsb_pos) & (2 ** $field->bits - 1);
            $field_labels[] = htmlentities($field->flat_labels[$index] ?? 'null', ENT_QUOTES);
        }

        $html = sprintf('0x%03X', $value) . '&ensp;<span class="text-muted">' . htmlentities($template->name, ENT_QUOTES) . ': ';
        $html .= implode("&mdash;", $field_labels);
        $html .= '</span>';

        return $html;
    }

    /**
     * @throws SubprocessException
     */
    public function render2($form, $html_field_name, $value): void {
        $type_value = $form->data ? $form->data[$this->name . "_type"] : '';

        if ($form->editing) {
            // Render <select> element

            $options = ['UNDEF' => 'Not defined',
                        'DIRECT' => 'Std. ID',
                        'DIRECT_EXTENDED' => 'Ext. ID'];

            // Iterate custom formats and add to <select>

            foreach (self::get_custom_formats() as $format_name => $format) {
                $options[$format_name] = $format_name;
            }

            echo "<select name='{$html_field_name}_type'>";
            foreach ($options as $key => $text)
                echo "<option value='$key'" . ($key == $type_value ? ' selected' : '') . ">" . htmlentities($text, ENT_QUOTES) . "</option>";
            echo "</select>";

            echo "<input type='hidden' id='$html_field_name' name='$html_field_name' value='" . htmlentities($value ? (string)$value : '0', ENT_QUOTES) . "'>";
        }
        else {
            // Render static view

            echo '<div class="value">';
            echo static::render_view($type_value, $value, allow_html: true);
            echo '</div>';
        }
    }

    /**
     * @throws SubprocessException
     */
    public static function render_view($type_value, $value, bool $allow_html): string {
        switch ($type_value) {
            case 'UNDEF': return 'Not defined';
            case 'DIRECT_EXTENDED': return sprintf("0x%08X", $value);

            default:
                if ($allow_html) {
                    // Check for custom format
                    $custom_formats = self::get_custom_formats();

                    if (array_key_exists($type_value, $custom_formats)) {
                        $str = self::present_id_html($value, $custom_formats[$type_value]);

                        return $str;
                    }
                }

                // general fallback, including case 'DIRECT'
                return sprintf("0x%03X", $value);
        }
    }

    public function translate_form_data(string $submitted_value): int {
        // FIXME, this will break when Message::set_can_id is updated to enforcing
        // Need to also look at _type and coerce ID to null if UNDEF
        return (int)$submitted_value;
    }

    public function translate_json_data(mixed $submitted_value): mixed
    {
        throw new \Exception("Not implemented");
    }

    public function validate_value(mixed $value): void {
        if ($value !== null) {
            if (!is_integer($value) || $value < 0) {
                throw new ValidationException("Expected non-negative integer");
            }
        }
    }
}
