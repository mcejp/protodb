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
 * @covers candb\service\EnumTypesService
 */
final class EnumTypesServiceTest extends DiceTest
{
    private const TEST_USERNAME = '_test';
    private const TEST_UNIT_ID = 42;

    public function testCanBeCreated(): void {
        /** @var candb\service\EnumTypesService $enumTypes */
        $enumTypes = $this->dice->create('candb\\service\\EnumTypesService');
        $this->assertNotEmpty($enumTypes);
    }

    public function testCanInsertAndUpdateEnumType(): void {
        /** @var candb\service\EnumTypesService $enumTypes */
        $enumTypes = $this->dice->create('candb\\service\\EnumTypesService');

        $enum = new \candb\model\EnumType(null, self::TEST_UNIT_ID, 'FooEnum', '');
        $enum->id = $enumTypes->insert($enum, self::TEST_USERNAME);
        $this->assertNotEmpty($enum->id);

        $enumTypes->updateItems($enum->id, [
            new \candb\model\EnumItem(null, $enum->id, 0, 'foo', 1, 'Bar!')
        ]);

        $enum->name = 'BarEnum';
        $enum->description = 'Brief';
        $enumTypes->update($enum, self::TEST_USERNAME);

        // Re-load and ensure updated
        $enum = $enumTypes->byId($enum->id);
        $this->assertSame('BarEnum', $enum->name);
        $this->assertSame('Brief', $enum->description);
    }
}
