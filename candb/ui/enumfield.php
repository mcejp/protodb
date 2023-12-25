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

// Perhaps should be called SelectField instead
class EnumField extends FormField {
    public $type = 'enum', $default;

    public $option_groups = null;
    private $value_lookup;

    public function __construct(public ?string $name, array $options, $default = NULL) {
        $this->option_groups = ['' => self::assoc_to_pairs($options)];
        $this->default = $default;

        foreach ($this->option_groups as $label => $options) {
            foreach ($options as $key_value) {
                [$key, $item_value] = $key_value;

                if ($item_value === null) {
                    throw new \InvalidArgumentException("item value must not be null");
                }

                $this->value_lookup[$key] = $item_value;
            }
        }
    }

    public static function with_option_groups($name, $option_groups) {
        $this_ = new self($name, []);

        $this_->option_groups = $option_groups;

        foreach ($this_->option_groups as $label => $options) {
            foreach ($options as $key_value) {
                [$key, $item_value] = $key_value;

                if ($item_value === null) {
                    throw new \InvalidArgumentException("item value must not be null");
                }

                $this_->value_lookup[$key] = $item_value;
            }
        }

        return $this_;
    }

    private static function assoc_to_pairs($array) {
        $out = [];
        foreach ($array as $key => $value)
            $out[] = [$key, $value];
        return $out;
    }

    public function render2($form, $name, $value): void {
        if ($form->editing) {
            echo "<select name='" . htmlentities($name, ENT_QUOTES) . "' class='form-control'>";

            $selected_key = $this->default;     // may also be null; in that case no option will get the `selected` attribute

            foreach ($this->option_groups as $label => $options) {
                foreach ($options as $key_value) {
                    [$key, $item_value] = $key_value;

                    if ($key == $value)
                        $selected_key = $key;
                }
            }

            $display_optgroups = (count($this->option_groups) != 1 || array_keys($this->option_groups)[0] !== '');

            foreach ($this->option_groups as $label => $options) {
                if ($display_optgroups) {
                    echo '<optgroup label="' . htmlentities($label, ENT_QUOTES) . '">';
                }

                foreach ($options as $key_value) {
                    [$key, $item_value] = $key_value;

                    echo '<option value="' . htmlentities((string)$key, ENT_QUOTES) . '"' . ($key == $selected_key ? ' selected' : '') . '>'
                        . htmlentities($item_value, ENT_QUOTES) . "</option>";
                }

                if ($display_optgroups) {
                    echo '</optgroup>';
                }
            }

            echo "</select>";
        }
        else {
            echo '<div class="value">';

            if (array_key_exists($value, $this->value_lookup)) {
                // TODO: escape or not?
                echo /*htmlentities($this->options[$value], ENT_QUOTES)*/ $this->value_lookup[$value];
            }
            else {
                // TODO: this can happen for example when a message is associated with a bus that the onwing node is not connected to
                // A proper solution is needed instead of this work-around.
                echo htmlentities((string)$value, ENT_QUOTES);
            }

            echo '</div>';
        }
    }

    /* You probably don't ever want to use this, due to validation etc.
     * public function setOptions($options) {
        $this->options = $options;
    }*/

    public function translate_form_data(string $submitted_value): mixed {
        // Meh. THis should make use of key_value_lookup
        foreach ($this->option_groups as $label => $options) {
            foreach ($options as $key_value) {
                [$key, $item_value] = $key_value;

                if ((string)$key === $submitted_value) {
                    if ($key === '') {
                        return null;
                    }
                    else {
                        return $key;
                    }
                }
            }
        }

        throw new \InvalidArgumentException("Invalid enum value");
    }

    /**
     * @param $submitted_value
     * @return mixed|null
     * @throws ValidationException
     */
    public function translate_json_data(mixed $submitted_value): mixed {
        // Meh. THis should make use of key_value_lookup
        foreach ($this->option_groups as $label => $options) {
            foreach ($options as $key_value) {
                [$key, $item_value] = $key_value;

                if ($key === $submitted_value) {
                    if ($key === '') {
                        return null;
                    }
                    else {
                        return $key;
                    }
                }
            }
        }

        $this->raise_validation_exception("Invalid enum value", $submitted_value);
    }

    public function validate_value(mixed $value): void {
        // Re-use this because the logic is identical except we don't care about the value
        $this->translate_json_data($value);
    }
}
