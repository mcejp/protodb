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
 * @covers candb\controller\IndexController
 */
final class IndexControllerTest extends CommonTest
{
    private static function contains_entity_by_id(array $haystack, int $id): bool
    {
        foreach ($haystack as $unit) {
            if ($unit->id === $id)
                return true;
        }

        return false;
    }

    public function testCanBeCreated(): void {
        /** @var candb\controller\IndexController $controller */
        $controller = $this->dice->create('candb\\controller\\IndexController');
        $this->assertNotEmpty($controller);
    }

    public function testHandleChangelogWorks(): void {
        /** @var candb\controller\IndexController $controller */
        $controller = $this->dice->create('candb\\controller\\IndexController');

        $result = $controller->handle_changelog();

        $this->assertNotEmpty($result);
    }

    public function testHandleIndexWorks(): void {
        /** @var candb\controller\IndexController $controller */
        $controller = $this->dice->create('candb\\controller\\IndexController');

        $result = $controller->handle_index(null);

        $this->assertInstanceOf('\candb\controller\HttpResult', $result);
        $this->assertEquals(200, $result->response_code);
        $this->assertStringContainsString('<a href="packages/1">TestPackage1</a>', $result->body);
    }

    public function testThatNewDashboardWorks(): void {
        /** @var candb\controller\IndexController $controller */
        $controller = $this->dice->create('candb\\controller\\IndexController');

        $result = $controller->handle_new_dashboard();

        $this->assertNotEmpty($result);
    }
}
