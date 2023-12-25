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

use candb\ui\IntegerField;
use candb\ui\TableField;

require_once 'DiceTest.php';

/**
 * @covers \candb\ui\TableField
 */
final class TableFieldTest extends DiceTest
{
    public function testMinAllowedValue(): void {
        $field = new TableField("fields", [
            ['name' => 'bit_size', 'title' => 'Size in bits', 'field' => new IntegerField(null, null, true, 1)],
        ], true, 'MessageFieldsFormTable');

        // Should fail
        $this->expectException(\candb\ui\ValidationException::class);
        $field->translate_form_data(json_encode([['bit_size' => -4]]));
    }
}
