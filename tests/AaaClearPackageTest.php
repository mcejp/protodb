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

final class AaaClearPackageTest extends DiceTest
{
    /** @var \candb\service\PackagesService */
    private $packages;

    /** @var \candb\service\UnitsService */
    private $units;

    /** @var \candb\utility\TestUtil */
    private $test_util;

    public function setUp(): void
    {
        parent::setUp();

        $this->packages = $this->dice->create('\candb\service\PackagesService');
        $this->units = $this->dice->create('\candb\service\UnitsService');
        $this->test_util = $this->dice->create('\candb\utility\TestUtil');
    }

    public function testClearPackage(): void
    {
        $package_id = $this->test_util->get_some_package_id();

        $package = $this->packages->byId($package_id, true);

        foreach ($package->units as $unit) {
            $this->units->delete_forever($unit);
        }

        $package = $this->packages->byId($package_id, true);
        $this->assertEmpty($package->units);

        // TODO: also reset buses etc.
    }
}
