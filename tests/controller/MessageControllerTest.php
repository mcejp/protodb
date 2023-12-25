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
 * @covers candb\controller\MessageController
 */
final class MessageControllerTest extends CommonTest
{
    /** @var \candb\controller\MessageController */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\MessageController');
        $this->controller->setCurrentUsername(\candb\utility\TestUtil::TEST_USERNAME);
    }

    private function delete_message_by_url(string $url): void
    {
        $url_components = explode('-', $url);
        $id = (int)end($url_components);
        $this->dice->create('candb\\service\\MessagesService')->delete_forever_by_id($id);
    }

    private function create_message_form_data(): array
    {
        return [
            "name" => "TestMessage",
            "can_id" => "900",
            "can_id_type" => "DIRECT",
            "bus_id" => "",
            "description" => "Test message #1",
            "tx_period" => "10",
            "timeout" => "200",
            "fields" => '[{"type":"int","bit_size":16,"name":"TestField1","description":"Test function #1","unit":"foo","factor":"0.1","offset":"0","min":"","max":"","array_length":1,"id":87},'.
                         '{"type":"int","bit_size":16,"name":"TestField2","description":"Test function #2","unit":"foo","factor":"0.1","offset":"0","min":"","max":"","array_length":1,"id":88},'.
                         '{"type":"int","bit_size":16,"name":"TestField3","description":"Test function #3","unit":"foo","factor":"0.1","offset":"0","min":"","max":"","array_length":1,"id":123},'.
                         '{"type":"int","bit_size":16,"name":"TestField4","description":"Test function #4","unit":"foo","factor":"0.1","offset":"0","min":"","max":"","array_length":1,"id":124}]',
            "buses" => '[]',
        ];
    }

    private function enum_field_contains_value(\candb\ui\EnumField $field, $needle): bool
    {
        foreach ($field->option_groups as $label => $options) {
            foreach ($options as $key_value) {
                [$key, $item_value] = $key_value;

                if ($item_value === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    private function insert_new_message(): int
    {
        $form_data = $this->create_message_form_data();

        \candb\ui\Form::injectFormData("message", $form_data);

        $result = $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);

        $url_components = explode('/', $result->headers['Location']);
        $id = (int)end($url_components);
        $this->assertNotEmpty($id);
        return $id;
    }

    public function testHandleIndexWorks(): void {
        $insert_new = $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 0);
        $this->assertNotNull($insert_new['message']->fields);

        $view_existing = $this->controller->handle_index(0, $this->test_util->get_some_message_id(), 0);
        $this->assertNotNull($view_existing['message']->fields);

        $edit_existing = $this->controller->handle_index(0, $this->test_util->get_some_message_id(), 1);
        $this->assertNotNull($edit_existing['message']->fields);
    }

    public function testCanInsertMessage(): void {
        $id = $this->insert_new_message();
    }

    public function testCanInsertMessageWithBusId(): void {
        $form_data = $this->create_message_form_data();
        $form_data["bus_id"] = (string)$this->some_bus_ids[0];

        \candb\ui\Form::injectFormData("message", $form_data);

        $result = $this->controller->handle_index($this->a_unit_id, 0, 1);
        $this->assertInstanceOf('\candb\controller\HttpResult', $result);
        $this->delete_message_by_url($result->headers['Location']);
    }

    public function testCanUpdateMessage(): void {
        $id = $this->insert_new_message();

        $form_data = $this->create_message_form_data();
        $form_data['timeout'] = '2987';
        $form_data['fields'] = '[{"type":"int","bit_size":16,"name":"RenamedField","description":"Front right wheel revolutions (not connected)","unit":"RPM","factor":"0.1","offset":"0","min":"","max":"","array_length":1},'.
                                '{"type":"int","bit_size":16,"name":"Wh_Rev_FL","description":"Front left wheel revolutions (not connected)","unit":"RPM","factor":"0.1","offset":"0","min":"","max":"","array_length":1},'.
                                '{"type":"int","bit_size":16,"name":"Wh_Rev_RR","description":"Rear right wheel revolutions","unit":"RPM","factor":"0.1","offset":"0","min":"","max":"","array_length":1},'.
                                '{"type":"int","bit_size":16,"name":"Wh_Rev_RL","description":"Rear left wheel revolutions","unit":"RPM","factor":"0.1","offset":"0","min":"","max":"","array_length":1}]';

        \candb\ui\Form::injectFormData("message", $form_data);

        $result = $this->controller->handle_index(0, $id, 1);
        $this->assertEquals(2987, $result['message']->timeout);
        $this->assertEquals('RenamedField', $result['message']->fields[0]->name);
    }

    public function testDeletedMessageIsDeleted(): void {
        $sql = <<<END
INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`, `valid`)
        VALUES (1, 1, NULL, 0, 'UNDEF', 'TestMessage1', 'Test message', '_db', '0');
END;

        $this->test_util->initialize_database_from_string($sql);

        $res = $this->controller->handle_index(0, 1, 0);
        $this->assertInstanceOf('candb\controller\HttpResult', $res);
        $this->assertEquals(\candb\controller\HttpResult::NOT_FOUND_CODE, $res->response_code);
    }

    public function testEnumsAreLoadedCorrectly(): void
    {
        $result = $this->controller->handle_index($this->a_unit_id, 0, 0);
        /** @var \candb\ui\EnumField */ $field_type = $result['form']->fields['fields']->columns[0]['field'];

        $this->assertTrue($this->enum_field_contains_value($field_type, 'Weekdays'));
        $this->assertEquals($this->an_enum_type_id, $field_type->translate_form_data((string)$this->an_enum_type_id));
    }

    public function testPackageNameIsLoaded(): void
    {
        $result = $this->controller->handle_index(0, $this->test_util->get_some_message_id(), 0);
        $sent_by = $result['sent_by'];
        $this->assertNotEmpty($sent_by);
        $this->assertNotEmpty($sent_by[0]->package_name);
    }

    public function testWillRejectIncompleteForm(): void {
        $form_data = $this->create_message_form_data();
        unset($form_data['fields']);

        \candb\ui\Form::injectFormData("message", $form_data);

        $this->expectExceptionMessage("Missing required field 'fields'");
        $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 1);
    }

    public function testWillRejectIncompleteForm2(): void {
        $form_data = $this->create_message_form_data();
        unset($form_data['timeout']);

        \candb\ui\Form::injectFormData("message", $form_data);

        $this->expectExceptionMessage("Missing required field 'timeout'");
        $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 1);
    }

    public function testWillRejectNegativeTimeout(): void {
        $form_data = $this->create_message_form_data();
        $form_data['timeout'] = '-1';

        $this->expect_value_out_of_range_exception($form_data);
    }

    public function testWillRejectZeroTxPeriod(): void {
        $form_data = $this->create_message_form_data();
        $form_data['tx_period'] = '0';

        $this->expect_value_out_of_range_exception($form_data);
    }

    private function expect_value_out_of_range_exception($form_data): void {
        \candb\ui\Form::injectFormData("message", $form_data);

        $this->expectExceptionMessage('Value out of range');
        $this->controller->handle_index($this->test_util->get_some_unit_id(), 0, 1);
    }
}
