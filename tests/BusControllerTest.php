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

require_once 'CommonTest.php';

/**
 * @covers candb\controller\BusController
 */
final class BusControllerTest extends CommonTest
{
    /** @var \candb\controller\BusController */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\BusController');
        $this->controller->setCurrentUsername(\candb\utility\TestUtil::TEST_USERNAME);
    }

    public function testCanHandleIndex(): void
    {
        $this->assertNotEmpty($this->controller->handle_index($this->some_bus_ids[0]));
    }
}
