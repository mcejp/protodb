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

require_once 'vendor/Parsedown.php';

class TextField extends FormField {
    private const CONTENT_PLAINTEXT = 'plain';
    private const CONTENT_MARKDOWN = 'md';

    private $required = false;
    private $maxLength = null;
    /** @var ?int */ private $min_value = null;
    private $empty_value_is_null = false;
    private $content_type = self::CONTENT_PLAINTEXT;

    public const TYPE_INTEGER = 'integer';
    public const TYPE_REAL = 'real';
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';        // pretty poor choice of a name

    public function __construct(public ?string $name = null,
                                public string $type = self::TYPE_TEXT,
                                public ?string $hint = null) {
    }

    public function get_max_length(): ?float {
        return $this->maxLength;
    }

    public function get_min_value(): ?int {
        return $this->min_value;
    }

    public function isRequired(): bool {
        return $this->required;
    }

    public function makeRequired(): TextField {
        $this->required = true;
        return $this;
    }

    public function render2($form, $name, $value): void {
        if ($form->editing) {
            if ($this->type === self::TYPE_INTEGER || $this->type === self::TYPE_REAL || $this->type === self::TYPE_TEXT) {
                if ($this->hint)
                    echo '<div class="input-group">';

                if ($this->type === self::TYPE_INTEGER) {
                    $type = 'number';
                    $step = 1;
                }
                else if ($this->type === self::TYPE_REAL) {
                    $type = 'text';
                    $step = null;
                }
                else {
                    $type = 'text';
                    $step = null;
                }

                echo "<input type='$type'" . ($step !== null ? " step='$step'" : "") .
                        " name='" . htmlentities($name, ENT_QUOTES) . "'" .
                        " value='" . htmlentities($value !== null ? (string)$value : '', ENT_QUOTES) . "'" .
                        " class='form-control'" .
                        ($this->required ? ' required' : '') .
                        ($this->maxLength !== null ? " maxlength='" . $this->maxLength . "'" : '') .
                        ($this->min_value !== null ? " min='" . $this->min_value . "'" : '') .
                        ">";

                if ($this->hint)
                    echo '<span class="input-group-addon">' . $this->hint . '</span></div>';
            }
            else {
                echo "<textarea name='" . htmlentities($name, ENT_QUOTES) . "' rows='5' class='form-control'" .
                        ($this->required ? ' required' : '') . ">" . htmlentities($value ?: '', ENT_QUOTES) . "</textarea>";

                if ($this->content_type === self::CONTENT_MARKDOWN) {
                    echo '<div class="small text-muted"><a href="https://guides.github.com/features/mastering-markdown/" target="_blank">Markdown</a>-enabled.</div>';
                }
            }
        }
        else {
            if ($value !== '') {
                echo '<div class="value">';

                switch ($this->content_type) {
                    case self::CONTENT_PLAINTEXT:
                        echo nl2br(htmlentities((string)$value, ENT_QUOTES));
                        break;

                    case self::CONTENT_MARKDOWN:
                        $parsedown = new \Parsedown();
                        echo $parsedown->text((string)$value);
                        break;
                }

                if ($this->hint)
                    echo ' ' . $this->hint;

                echo '</div>';
            }
            else
                echo '&ndash;';
        }
    }

    public function set_empty_value_is_null(bool $empty_value_is_null) {
        $this->empty_value_is_null = $empty_value_is_null;
    }

    public function set_min_value($min_value) {
        assert($this->type == self::TYPE_INTEGER || $this->type == self::TYPE_REAL);

        $this->min_value = $min_value;
    }

    public function set_max_length(float $maxLength): TextField {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function translate_form_data(string $submitted_value): mixed {
        if ($submitted_value === '' && $this->required) {
            throw new \InvalidArgumentException("Expected non-empty value");
        }

        if ($this->empty_value_is_null && $submitted_value === '') {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_INTEGER:
                if (!is_numeric($submitted_value))
                    throw new \InvalidArgumentException("Expected integer");

                $value = (int)$submitted_value;

                if ($this->min_value !== null && $value < $this->min_value) {
                    throw new \InvalidArgumentException("Value out of range");
                }

                return $value;

            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                return $submitted_value;

            default:
                throw new \Exception("Not implemented " . $this->type);
        }
    }

    public function translate_json_data(mixed $submitted_value): mixed {
        switch ($this->type) {
            case self::TYPE_INTEGER:
                if ($this->empty_value_is_null && $submitted_value === null) {
                    return null;
                }

                if (!is_integer($submitted_value)) {
                    $this->raise_validation_exception("Expected integer", $submitted_value);
                }

                if ($this->min_value !== null && $submitted_value < $this->min_value) {
                    $this->raise_validation_exception("Value out of range", $submitted_value);
                }

                return $submitted_value;

            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                if (!is_string($submitted_value)) {
                    $this->raise_validation_exception("Expected string", $submitted_value);
                }

                if ($this->empty_value_is_null && $submitted_value === '') {
                    return null;
                }

                return $submitted_value;

            default:
                throw new \Exception("Not implemented " . $this->type);
        }
    }

    public function use_markdown(): void {
        $this->content_type = self::CONTENT_MARKDOWN;
    }

    /**
     * @param $value
     * @throws ValidationException
     */
    public function validate_value($value): void {
        // Re-use this because the logic is identical except we don't care about the value
        $this->translate_json_data($value);
    }
}
