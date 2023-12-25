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

class MessageBandwidthStatistics
{
    /** @var int|null */    public $frequency;
    /** @var int */         public $num_fields;
    /** @var int */         public $useful_bits;
    /** @var int|null */    public $useful_bandwidth;
    /** @var int|null */    public $total_bandwidth;

    public function __construct(?int $frequency, int $num_fields, int $useful_bits, ?int $useful_bandwidth, ?int $total_bandwidth)
    {
        $this->frequency = $frequency;
        $this->num_fields = $num_fields;
        $this->useful_bits = $useful_bits;
        $this->useful_bandwidth = $useful_bandwidth;
        $this->total_bandwidth = $total_bandwidth;
    }
}

final class Message
{
    const CAN_MAX_MESSAGE_LENGTH = 8;
    const CAN_MAX_ARRAY_SIZE = 64;

    const CAN_ID_TYPE_EXTENDED = 'DIRECT_EXTENDED';
    const CAN_ID_TYPE_UNDEF = 'UNDEF';

    const FRAME_TYPE_CAN_STD = 'CAN_STD';
    const FRAME_TYPE_CAN_EXT = 'CAN_EXT';

    public $id, $node_id, $bus_id, $name, $description, $tx_period, $timeout, $who_changed, $when_changed;
    public $operation, $owner_name;        // TODO: analyze and maybe remove these
    public $package_id, $package_name;
    /** @var MessageField[] */
    public $fields;
    public $num_bytes;

    private string $can_id_type;
    private ?int $can_id;

    public function __construct(?int $id, int $node_id, ?int $bus_id, ?int $can_id, string $can_id_type, string $name,
            string $description, ?int $tx_period = null, ?int $timeout = null,
                                private ?array $buses = [])
    {
        $this->id = $id;
        $this->node_id = $node_id;
        $this->bus_id = $bus_id;
        $this->set_can_id($can_id_type, $can_id);
        $this->name = $name;
        $this->description = $description;
        $this->tx_period = $tx_period;
        $this->timeout = $timeout;
    }

    public function url() { return $this->id ? static::s_url($this->id) : NULL; }
    public function url_delete() { return $GLOBALS['base_path'] . "messages/{$this->id}/delete"; }
    public function urlDrc() { return $GLOBALS['base_path'] . "messages/{$this->id}/drc"; }
    public function editing_url() { return $this->id ? static::s_url($this->id).'/edit' : NULL; }
    public function title() { return $this->name ? $this->name : "New message"; }

    public static function s_url($message_id) { return $GLOBALS['base_path'] . "messages/{$message_id}"; }
    public static function s_new_url($unit_id) { return $GLOBALS['base_path'] . "messages/new-{$unit_id}"; }

    public function warnings() {
        $warnings = [];

        if ($this->num_bytes !== null && $this->num_bytes > self::CAN_MAX_MESSAGE_LENGTH)
            $warnings[] = "The message is too long (" . $this->num_bytes . " bytes)! Maximum is " . self::CAN_MAX_MESSAGE_LENGTH . " bytes.";

        if (!self::valid_c_ident($this->name))
            $warnings[] = "'$this->name' is not a valid identifier in C. Code generation will not be possible.";

        if (!self::valid_c_comment($this->description))
            $warnings[] = "Invalid characters in Description - might cause issues in generated code";

        if ($this->timeout === 0 || $this->timeout === '0') {
            $warnings[] = "Message timeout is set to '0 ms'. Either use a non-zero value, or leave the field empty to specify infinite timeout.";
        }

        // check field names
        foreach ($this->fields as $field) {
            if ($field->name && !self::valid_c_ident($field->name))
                $warnings[] = "'{$field->name}' is not a valid identifier in C. Code generation will not be possible.";

            if (!self::valid_c_comment($field->description))
                $warnings[] = "{$field->name}: Invalid characters in Description - might cause issues in generated code";

        }

        return $warnings;
    }

    // TODO: document!
    public function field_at_bit($bit): ?array {
        foreach ($this->fields as $f) {
            for ($i = 0; $i < $f->array_length; $i++) {
                $bits_into = 0;
                foreach ($f->ranges[$i] as $r) {
                    if ($r[0] * 8 + $r[1] <= $bit && $r[0] * 8 + $r[1] + $r[2] > $bit)
                        return [$f, $i, $r[2], $bits_into];
                    $bits_into += $r[2];
                }
            }
        }

        return NULL;
    }

    public function get_bandwidth_statistics(): MessageBandwidthStatistics
    {
        $this->layout();

        $useful_bits = array_sum(array_map(fn($field) => $field->bit_size * $field->array_length, $this->fields));

        // Assuming STD ID!
        // https://en.wikipedia.org/wiki/CAN_bus#Base_frame_format
        // http://www.mrtc.mdh.se/publications/0351.pdf
        $total_bits = 47 + 8 * $this->num_bytes;
        $total_bits_with_stuffing = $total_bits + floor((34 + 8 * $this->num_bytes - 1) / 4);

        if ($this->tx_period > 0) {
            $frequency = 1000 / $this->tx_period;
            $useful_bandwidth = (int)floor($useful_bits * $frequency);
            $total_bandwidth = (int)ceil($total_bits_with_stuffing * $frequency);
            $frequency = (int)$frequency;
        }
        else {
            $frequency = null;
            $useful_bandwidth = null;
            $total_bandwidth = null;
        }

        return new MessageBandwidthStatistics($frequency, count($this->fields), $useful_bits, $useful_bandwidth, $total_bandwidth);
    }

    public function get_buses(): array {
        if ($this->buses === null) {
            throw new \Exception("Buses not loaded");
        }

        return $this->buses;
    }

    public function get_can_id(): ?int {
        assert($this->can_id === null || is_int($this->can_id));
        return $this->can_id;
    }

    public function get_can_id_type(): string {
        return $this->can_id_type;
    }

    public function get_frame_type(): string
    {
        if ($this->can_id_type == self::CAN_ID_TYPE_EXTENDED) {
            return self::FRAME_TYPE_CAN_EXT;
        }
        else {
            // Fallback also for undefined ID. It would probably make sense to have a separate FRAME_TYPE_UNSPECIFIED,
            // but that would be a breaking change for JSON 2.0 and everything that consumes it.
            return self::FRAME_TYPE_CAN_STD;
        }
    }

    public function id_to_hex_string(): ?string {
        if ($this->can_id === null) {
            return null;
        }

        switch ($this->get_frame_type()) {
            case self::FRAME_TYPE_CAN_STD: return sprintf("0x%03X", $this->can_id);
            case self::FRAME_TYPE_CAN_EXT: return sprintf("0x%08X", $this->can_id);
            default: throw new \InvalidArgumentException("Unhandled frame type");
        }
    }

    // FIXME: this should not be a stateful operation!
    public function layout() {
        // Unfortunately, this needs to be kept in sync with "Message.layout()" in candb.py manually
        $usage_map = [];

        foreach ($this->fields as $f) {
            for ($i = 0; $i < $f->array_length; $i++) {
                $b = $f->bit_size;
                $f->ranges[$i] = [];

                if ($b % 8 == 0) {
                    // multiple of 8 bits - always append to the end

                    if ($f->start_bit < 0) {
                        // when placing the first element of the array, save the absolute position within the frame
                        $f->start_bit = count($usage_map) * 8;
                    }

                    for ($byte = 0; $byte < $b / 8; $byte++) {
                        $f->ranges[$i][] = [count($usage_map), 0, 8];
                        $usage_map[] = 8;
                    }
                }
                else {
                    // fill remaining space in last byte, or start a new one
                    // (the entire array will be placed contiguously)

                    if (count($usage_map) > 0) {
                        // there is at least one byte -- check if if has remaining space
                        $byte_index = count($usage_map) - 1;
                        $used_bits = $usage_map[$byte_index];

                        if ($used_bits < 8) {
                            if ($f->start_bit < 0) {
                                // when placing the first element of the array, save the absolute position within the frame
                                $f->start_bit = $byte_index * 8 + $used_bits;
                            }

                            // use as many bits as possible in this byte
                            $use = min($b, 8 - $used_bits);

                            $f->ranges[$i][] = [$byte_index, $used_bits, $use];
                            $b -= $use;
                            $usage_map[$byte_index] += $use;
                        }
                    }

                    // otherwise, place at the end
                    if ($f->start_bit < 0) {
                        $f->start_bit = count($usage_map) * 8;
                    }

                    // any bits left over? - put them at the end
                    if ($b != 0) {
                        while ($b > 0) {
                            $use = ($b > 8) ? 8 : $b;
                            $f->ranges[$i][] = [count($usage_map), 0, $use];
                            $usage_map[] = $use;
                            $b -= $use;
                        }
                    }
                }
            }
        }

        $this->num_bytes = count($usage_map);
    }

    public function find_field_by_id($field_id) {
        foreach ($this->fields as $field)
            if ($field->id == $field_id)
                return $field;

        return NULL;
    }

    public function format_tx_period(): string {
        if ($this->tx_period === null) {
            return '&mdash;';
        }
        else {
            return $this->tx_period . ' ms';
        }
    }

    // TODO: this certainly doesn't belong in model
    public function print_nice_layout_lsb_first() {
        for ($byte = 0; $byte < $this->num_bytes; $byte++) {
            echo "<tr><th>byte $byte</th>";

            for ($b = 0; $b < 8; ) {
                list($f, $i, $span, $bits_into) = $this->field_at_bit($byte * 8 + $b);

                $text = "";
                if ($f != null) {
                    // FIXME: Do not use "_" to indicate placeholders
                    if ($f->name != '_' && $f->name !== '') {
                        $text = ($f->array_length != 1) ? "{$f->name}[$i]" : "{$f->name}";
                    }

                    if ($span < $f->bit_size) {
                        $text .= "<sub class='text-muted'>".$bits_into.":".($bits_into + $span - 1)."</sub>";
                    }

                    $b += $span;
                }
                else {
                    $span = 1;
                    $b += 1;
                }

                if (!$text)
                    $text = "<i class='text-muted'>reserved</i>";

                echo "<td colspan='{$span}'>$text</td>";
            }

            echo "</tr>";
        }
    }

    // TODO: this certainly doesn't belong in model
    public function print_nice_layout_msb_first() {
        for ($byte = 0; $byte < $this->num_bytes; $byte++) {
            echo "<tr><th>byte $byte</th>";

            for ($b = 7; $b >= 0; ) {
                list($f, $i, $span, $bits_into) = $this->field_at_bit($byte * 8 + $b);

                $text = "";
                if ($f != null) {
                    // FIXME: Do not use "_" to indicate placeholders
                    if ($f->name != '_' && $f->name !== '') {
                        $text = ($f->array_length != 1) ? "{$f->name}[$i]" : "{$f->name}";
                    }

                    if ($span < $f->bit_size) {
                        $text .= "<sub class='text-muted'>".$bits_into.":".($bits_into + $span - 1)."</sub>";
                    }

                    $b -= $span;
                }
                else {
                    $span = 1;
                    $b -= 1;
                }

                if (!$text)
                    $text = "<i class='text-muted'>reserved</i>";

                echo "<td colspan='{$span}'>$text</td>";
            }

            echo "</tr>";
        }
    }

    /**
     * @param int[]|null $buses List of associated buses; when set to null, a call to {get_buses} triggers an exception
     * @return void
     */
    public function set_buses(?array $buses): void {
        $this->buses = $buses;
    }

    // TODO: $can_id_type more strongly typed
    public function set_can_id(string $can_id_type, ?int $can_id): void {
        if ($can_id_type !== self::CAN_ID_TYPE_UNDEF && $can_id === null) {
            throw new \InvalidArgumentException("Expected non-null ID");
        }

        if ($can_id_type === self::CAN_ID_TYPE_UNDEF && $can_id !== null) {
            //throw new \InvalidArgumentException("Expected null ID");

            // coerce ID to null until database fully migrated (issue #98)
            // mirrors protodb.SqlProtocolDatabase.get_message_by_id
            $can_id = null;
        }

        $this->can_id_type = $can_id_type;
        $this->can_id = $can_id;
    }

    public function validate(): void {
        if ($this->name === null || $this->name === '')
            throw new \InvalidArgumentException("Message name is required");
    }

    private static function valid_c_ident($str) {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $str) === 1;
    }

    private static function valid_c_comment($str) {
        return strstr($str, '*/') === FALSE && preg_match('/[^\x09\x0a\x0d\x20-\x7f]/', $str) !== 1;
    }
}
