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
 * @covers candb\service\PackagesService
 */
final class PackagesServiceTest extends CommonTest
{
    /** @var \candb\service\PackagesService */
    private $packages;

    public function setUp(): void
    {
        parent::setUp();

        $this->packages = $this->dice->create('\candb\service\PackagesService');
    }

    public function testCanInsertPackage(): void
    {
        $all_packages = $this->packages->allPackageNames();
        $id = max(array_keys($all_packages)) + 1;

        $package = new \candb\model\Package($id, 'Test', 'Test package.');
        $id = $this->packages->insert($package, \candb\utility\TestUtil::TEST_USERNAME);
        $this->assertNotEmpty($id);
    }

    public function testBasicOperationsWork(): void {
        $this->assertNotEmpty($this->packages->allPackageNames());

        $all = $this->packages->get_all(with_buses: true, with_nodes: true, with_messages: true);
        $this->assertNotEmpty($all);
        $this->assertNotEmpty($all[1]->buses);
        $this->assertNotEmpty($all[1]->units);
        $this->assertNotEmpty($all[1]->units[0]->messages);
    }

    public function testByIdWorks(): void {
        $this->assertNotEmpty($this->packages->byId($this->a_package_id,
                true, true, true));
    }

    public function testByNameWorks(): void {
        $this->assertNotEmpty($this->packages->by_name('TestPackage1',
                true, true, true));
    }
}
