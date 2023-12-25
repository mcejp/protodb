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

require_once 'DiceTest.php';

abstract class CommonTest extends DiceTest
{
    /** @var \candb\utility\TestUtil */
    protected $test_util;

    /** @var int */ protected $a_bus_id;
    /** @var int */ protected $a_message_id;
    /** @var int */ protected $a_package_id;
    /** @var int */ protected $a_unit_id;
    /** @var int */ protected $an_enum_type_id;
    /** @var int[] */ protected $some_bus_ids;

    public function setUp(): void
    {
        parent::setUp();

        $this->test_util = $this->dice->create('\candb\utility\TestUtil');
        $this->test_util->initialize_database('DefaultDataset');
        $this->a_message_id = 1;
        $this->a_package_id = 1;
        $this->a_bus_id = 1;
        $this->a_unit_id = 1;
        $this->an_enum_type_id = 1;
        $this->some_bus_ids = [1];
    }

    protected function create_a_message(): \candb\model\Message
    {
        return new \candb\model\Message(null, $this->a_unit_id, null, 123, 'DIRECT', 'TestMessage', 'Test message.');
    }

    protected function create_a_unit(): \candb\model\Unit
    {
        return new \candb\model\Unit(null, $this->a_package_id, 'test', 'test', null, "Foo", "Bar");
    }
}
