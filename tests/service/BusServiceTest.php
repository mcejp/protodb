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

require_once 'tests/DiceTest.php';

/**
 * @covers candb\service\BusService
 */
final class BusServiceTest extends CommonTest
{
    /** @var \candb\service\BusService */
    private $bus_service;

    public function setUp(): void
    {
        parent::setUp();

        $this->bus_service = $this->dice->create('\candb\service\BusService');
    }

    public function testBasicOperationsWork(): void
    {
        $this->assertSame([
            1 => "TestPackage1 - Bus1 (1000 kbit/s)",
            2 => "TestPackage1 - Bus2 (1000 kbit/s)",
            3 => "TestPackage1 - Bus3 (500 kbit/s)",
        ], $this->bus_service->all_bus_names());
    }

    public function testByIdWorks(): void
    {
        $this->assertNotEmpty($this->bus_service->by_id($this->a_bus_id));
    }

    public function testByUnitIdWorks(): void
    {
        $this->assertGreaterThanOrEqual(2, count($this->bus_service->by_unit_id($this->a_unit_id)));
    }
}
