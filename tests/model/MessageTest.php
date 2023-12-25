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

require_once 'tests/CommonTest.php';

/**
 * @covers candb\model\Message
 */
final class MessageTest extends DiceTest
{
    public function testStart_bitIsSetCorrectlyForArrays(): void {
        $msg = new \candb\model\Message(null, 1, null, 100, 'DIRECT', 'Test', 'Test');
        $msg->fields[] = new \candb\model\MessageField(1, 1, 0, 'Array', '...', 'uint', 16, 2, '', '', '', '', '');
        $msg->layout();

        $this->assertEquals(0, $msg->fields[0]->start_bit);
    }
}
