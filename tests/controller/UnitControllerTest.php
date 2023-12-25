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
 * @covers candb\controller\UnitController
 */
final class UnitControllerTest extends CommonTest
{
    /** @var \candb\controller\UnitController */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\UnitController');
        $this->controller->setCurrentUsername(\candb\utility\TestUtil::TEST_USERNAME);
    }

    private function delete_unit_by_id(int $id): void
    {
        $this->dice->create('candb\\service\\UnitsService')->delete_forever_by_id($id);
    }

    private function create_new_unit(): array
    {
        return [
            "name" => "TestUnit",
            "description" => "Test unit.",
            "authors_hw" => "foo",
            "authors_sw" => "bar",
            "code_model_version" => '1',
            "bus_links" => '[]',
            "sent_messages" => '[]',
            "received_messages" => '[]',
        ];
    }

    private function insert_new_unit(): int
    {
        $form_data = $this->create_new_unit();

        \candb\ui\Form::injectFormData("unit", $form_data);

        $result = $this->controller->handle_index($this->test_util->get_some_package_id(), 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);

        $url_components = explode('/', $result->headers['Location']);
        $id = (int)end($url_components);
        $this->assertNotEmpty($id);
        return $id;
    }

    public function testCanInsertUnit(): void {
        $id = $this->insert_new_unit();
        $this->delete_unit_by_id($id);
    }

    public function testCanSaveUnitWithMessageLink(): void {
        // Test for a regression caused by adding a UNIQUE key over
        // message_node(node_id, message_id, operation)

        // Prepare a unit with a message link
        $form_data = $this->create_new_unit();
        $form_data['sent_messages'] = json_encode([['message_id' => $this->a_message_id]]);

        // Save once
        \candb\ui\Form::injectFormData("unit", $form_data);

        $result = $this->controller->handle_index($this->a_package_id, 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);

        // Parse new ID
        $url_components = explode('/', $result->headers['Location']);
        $unit_id = (int)end($url_components);
        $this->assertNotEmpty($unit_id);

        // Re-query
        $result = $this->controller->handle_index(0, $unit_id, 1);

        $link_id = (int)$result['form']->data['sent_messages'][0]['id'];
        $this->assertNotEmpty($link_id);

        // Attempt to save a second time
        $form_data['sent_messages'] = json_encode([['id' => $link_id, 'message_id' => $this->a_message_id]]);
        \candb\ui\Form::injectFormData("unit", $form_data);

        $result = $this->controller->handle_index(0, $unit_id, 1);
        $this->assertNotEmpty($result);
    }

    public function testCanSetUnitCodeModelVersionOnInsert(): void {
        $form_data = $this->create_new_unit();
        $form_data['code_model_version'] = '2';

        \candb\ui\Form::injectFormData("unit", $form_data);

        $result = $this->controller->handle_index($this->test_util->get_some_package_id(), 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);

        $url_components = explode('/', $result->headers['Location']);
        $id = (int)end($url_components);
        $this->assertNotEmpty($id);

        $result = $this->controller->handle_index(0, $id, 1);
        $this->assertSame(2, $result['unit']->code_model_version);

        $this->delete_unit_by_id($id);
    }

    public function testCanSetUnitCodeModelVersionOnUpdate(): void {
        $id = $this->insert_new_unit();

        $form_data = $this->create_new_unit();
        $form_data['code_model_version'] = '2';

        \candb\ui\Form::injectFormData("unit", $form_data);

        $result = $this->controller->handle_index(0, $id, 1);
        $this->assertSame(2, $result['unit']->code_model_version);

        $this->delete_unit_by_id($id);
    }

    public function testDeletedUnitIsDeleted(): void {
        $sql = <<<END
INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '0');
END;

        $this->test_util->initialize_database_from_string($sql);

        $res = $this->controller->handle_index(0, 1, 0);
        $this->assertInstanceOf('candb\controller\HttpResult', $res);
        $this->assertEquals(\candb\controller\HttpResult::NOT_FOUND_CODE, $res->response_code);
    }

    public function testEmptyNameIsRejected(): void {
        $form_data = $this->create_new_unit();
        $form_data['name'] = '';

        \candb\ui\Form::injectFormData("unit", $form_data);

        $this->expectException("InvalidArgumentException");
        $this->controller->handle_index(0, $this->a_unit_id, 1);
    }

    public function testHandleIndexWorks(): void {
        $this->assertNotEmpty($this->controller->handle_index($this->test_util->get_some_package_id(), 0, 0));

        $result = $this->controller->handle_index(0, $this->a_unit_id, 0);
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($result['unit']->messages);
    }

    public function testLastChangeDateIsRetrieved(): void {
        $result = $this->controller->handle_index(0, $this->a_unit_id, 0);
        $this->assertNotEmpty($result['unit']->when_changed);
        $this->assertNotEmpty($result['unit']->who_changed);
    }
}
