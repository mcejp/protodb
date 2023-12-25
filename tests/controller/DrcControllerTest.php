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
 * @covers candb\controller\DrcController
 */
final class DrcControllerTest extends CommonTest
{
    /** @var \candb\controller\DrcController */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\DrcController');
    }

    public function testCanRunOnPackage(): void {
        $this->assertNotEmpty($this->controller->handle_unit($this->a_package_id, 1));
    }

    public function testCanRunOnUnit(): void {
        $this->assertNotEmpty($this->controller->handle_unit($this->a_unit_id, 1));
    }

    public function testHandleAllWorks(): void {
        $this->assertNotEmpty($this->controller->handle_all());
    }

    public function testHandlePackageWorks(): void {
        $this->assertNotEmpty($this->controller->handle_package($this->a_package_id));
    }

    // TODO: more tests
}
