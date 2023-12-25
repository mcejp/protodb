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
 * @covers \candb\controller\AdminManagement
 */
final class AdminManagementControllerTest extends CommonTest
{
    /** @var \candb\controller\AdminManagement */ private $controller;
    /** @var \candb\service\UnitsService */ private $units;

    const sql_for_unit_copy = <<<END
INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());
INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('2', 'TestPackage2', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `enum_type` (`id`, `node_id`, `name`, `description`, `who_changed`)
        VALUES ('1', '1', 'Weekdays', 'Test enum.', '_db');

INSERT INTO `enum_item` (`id`, `enum_type_id`, `position`, `name`, `value`, `description`)
        VALUES ('1', '1', '0', 'monday', '0', 'Monday'),
               ('2', '1', '1', 'tuesday', '1', 'Tuesday'),
               ('7', '1', '6', 'sunday', '6', 'Sunday');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (1, 1, NULL, 0, 'UNDEF', 'TestMessage1', 'Test message', '_db');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (2, 1, NULL, 0, 'UNDEF', 'TestMessage2', 'Test message', '_db');

-- use the unit's enum as the type of this field
INSERT INTO `message_field` (`id`, `message_id`, `position`, `name`, `description`, `type`, `bit_size`, `array_length`)
		VALUES (1, 2, 0, 'MissingDescription', '', '1', 1, 1);

INSERT INTO `message_node` (`id`, `node_id`, `message_id`, `operation`) VALUES (1, 1, 1, 'SENDER');
INSERT INTO `message_node` (`id`, `node_id`, `message_id`, `operation`) VALUES (2, 1, 2, 'RECEIVER');
END;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\AdminManagement');
        $this->controller->setCurrentUsername(\candb\utility\TestUtil::TEST_USERNAME);

        $this->units = $this->dice->create('\candb\service\UnitsService');
    }

    public function testCanBeCreated(): void {
        $this->assertNotEmpty($this->controller);
    }

    public function testHandleIndex(): void {
        $this->test_util->initialize_database_from_string(self::sql_for_unit_copy);
        \candb\ui\Form::injectFormData("unit_copy", ['unit_id' => '1', 'package_id' => '2']);
        $this->assertNotEmpty($this->controller->handle_index());
    }

    public function testCopyUnit(): void {
        $this->test_util->initialize_database_from_string(self::sql_for_unit_copy);

        $new_unit_id = $this->controller->copy_unit(1, 2);

        $this->assertNotEquals(1, $new_unit_id);

        $new_unit = $this->units->byId($new_unit_id, true, true, true);

        // verify that Enums were copied
        $this->assertNotEquals(1, $new_unit->enum_types[0]->id);

        // verify that Sent & Received Messages were copied and re-targeted
        $this->assertEquals($new_unit->sent_messages[0]['id'], (string) $new_unit->messages[0]->id);
        $this->assertEquals($new_unit->received_messages[0]['id'], (string) $new_unit->messages[1]->id);

        // TODO: should make sure that copy_unit rejects invalid package_id
    }
}
