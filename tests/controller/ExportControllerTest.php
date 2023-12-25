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
 * @covers candb\controller\ExportController
 */
final class ExportControllerTest extends CommonTest
{
    /** @var \candb\controller\ExportController */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->dice->create('\candb\controller\ExportController');
    }

    // FIXME: this test is crap. data set should be explicitly specified in it
    public function testCanFilterByBus(): void {
        $package_id = 1;
        $bus_id_1 = 1;
        $bus_id_2 = 2;

        $response = $this->controller->handle_package_json($package_id, $bus_id_1);
        $data = json_decode($response->body);
        //$count1 = count($data[0]->messages);
        $this->assertNotEmpty($data[0]->messages);

        $response = $this->controller->handle_package_json($package_id, $bus_id_2);
        $data = json_decode($response->body);
        //$count2 = count($data[0]->messages);
        $this->assertEmpty($data);
    }

    public function testHandleJsonWorks(): void {
        $json_response = $this->controller->handle_json($this->test_util->get_some_unit_id());

        $this->assertInstanceOf('\\candb\\controller\\HttpResult', $json_response);
        $this->assertEquals('application/json', $json_response->headers['Content-Type']);
    }

    public function testHandleJson2Works(): void {
        $json_response = $this->controller->handle_json2_buses_of_package(1);

        $this->assertInstanceOf('\\candb\\controller\\HttpResult', $json_response);
        $this->assertEquals('application/json', $json_response->headers['Content-Type']);
    }

    public function testJson2ExportEquivalence(): void {
        $json_response = $this->controller->handle_json2_buses_of_package(1);
        $this->assertInstanceOf('\\candb\\controller\\HttpResult', $json_response);
        $this->assertEquals('application/json', $json_response->headers['Content-Type']);

        $json_response2 = $this->controller->handle_json2_buses_of_package_protodb(1);
        $this->assertInstanceOf('\\candb\\controller\\HttpResult', $json_response2);
        $this->assertEquals('application/json', $json_response2->headers['Content-Type']);

        $this->assertEquals($json_response->body, $json_response2->body);
    }

    public function testHandlePackageJsonWorks(): void {
        $this->assertNotEmpty($this->controller->handle_package_json($this->test_util->get_some_package_id()));
    }
}
