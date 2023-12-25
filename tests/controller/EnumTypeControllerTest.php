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
 * @covers candb\controller\EnumTypeController
 */
final class EnumTypeControllerTest extends CommonTest
{
    /** @var candb\controller\EnumTypeController */ private $controller;
    /** @var candb\service\EnumTypesService */ private $enum_types;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\EnumTypeController');
        $this->controller->setCurrentUsername(\candb\utility\TestUtil::TEST_USERNAME);

        $this->enum_types = $this->dice->create('\candb\service\EnumTypesService');
    }

    public function testHandleIndexWorks(): void
    {
        $insert_new = $this->controller->handle_index($this->a_unit_id, 0);
        $this->assertNotNull($insert_new['enum_type']->items);

        $view_existing = $this->controller->handle_index(0, $this->an_enum_type_id);
        $this->assertNotNull($view_existing['enum_type']->items);

        $edit_existing = $this->controller->handle_index(0, $this->an_enum_type_id, 1);
        $this->assertNotNull($edit_existing['enum_type']->items);
    }

    public function testCanViewEnumType(): void {
        $result = $this->controller->handle_index(0, $this->an_enum_type_id, 0);
        $this->assertNotEmpty($result['enum_type']->id);
    }

    public function testCanInsertEnumType(): void {
        $form_data = [
            'name' => 'Test',
            'description' => 'Test',
            'items' => '[{"name": "FOO", "description": "Foo.", "value": 13}, {"name": "BAR", "description": "Bar.", "value": 17}]',
        ];

        \candb\ui\Form::injectFormData("enum_type", $form_data);

        $result = $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);

        $url_components = explode('/', $result->headers['Location']);
        $id = (int)end($url_components);
        $enum = $this->enum_types->byId($id);

        $this->assertCount(2, $enum->items);
    }

    public function testNewStateIsReturnedAfterUpdating(): void {
        $this->test_util->initialize_database_from_string(<<<END
            INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());

            INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
                    VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

            INSERT INTO `enum_type` (`id`, `node_id`, `name`, `description`, `who_changed`)
                    VALUES ('1', '1', 'Weekdays', 'Test enum.', '_db');

            INSERT INTO `enum_item` (`id`, `enum_type_id`, `position`, `name`, `value`, `description`)
                    VALUES ('1', '1', '0', 'monday', '0', 'Monday'),
                           ('2', '1', '1', 'tuesday', '1', 'Tuesday'),
                           ('7', '1', '6', 'sunday', '6', 'Sunday');
END
        );

        $form_data = [
            'name' => 'Test',
            'description' => 'Test',
            'items' => '[{"name": "FOO", "description": "Foo.", "value": 13}, {"name": "BAR", "description": "Bar.", "value": 17}]',
        ];

        \candb\ui\Form::injectFormData("enum_type", $form_data);

        $result = $this->controller->handle_index(1, 1, 1);

        $this->assertCount(2, $result['enum_type']->items);
    }
}
