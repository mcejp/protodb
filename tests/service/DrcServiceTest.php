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
 * @covers candb\service\drc\DrcService
 */
final class DrcServiceTest extends DiceTest
{
    /** @var \candb\service\drc\DrcService */
    private $drc;

    /** @var \candb\service\MessagesService */
    private $messages;

    /** @var \candb\utility\TestUtil */
    private $testUtil;

    public function setUp(): void
    {
        parent::setUp();

        $this->drc = $this->dice->create('candb\\service\\drc\\DrcService');
        $this->messages = $this->dice->create('candb\\service\\MessagesService');
        $this->testUtil = $this->dice->create('candb\\utility\\TestUtil');
    }

    public function testEnumBitsError(): void
    {
        $unit_id = 1;

        // Run DRC and check results
        $this->drc->run_for_unit($unit_id);
        $incidents = $this->drc->incidents_by_unit($unit_id);

        $this->assertNotEmpty($this->find_array_items_matching($incidents,
            ['description' => 'Field is too small for the chosen data type']));
    }

    public function testMessageTimeout0msWarning(): void
    {
        $unit_id = $this->testUtil->get_some_unit_id();

        // Create a message for testing
        $message = new \candb\model\Message(null, $unit_id, null, 0, 'UNDEF', 'TestMessage', 'Test', null, 0);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        // Run DRC and check results
        $this->drc->run_for_unit($unit_id);
        $incidents = $this->drc->incidents_by_unit($unit_id);

        $this->assertNotEmpty($this->find_array_items_matching($incidents,
            ['message_id' => $message->id, 'description' => 'Message timeout is set to an invalid value of 0 ms']));

        $this->messages->delete_forever($message);
    }

    public function testMessageTxPeriod0msWarning(): void
    {
        $unit_id = $this->testUtil->get_some_unit_id();

        // Create a message for testing
        $message = new \candb\model\Message(null, $unit_id, null, 0, 'UNDEF', 'TestMessage', 'Test', 0, null);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        // Run DRC and check results
        $this->drc->run_for_unit($unit_id);
        $incidents = $this->drc->incidents_by_unit($unit_id);

        $this->assertNotEmpty($this->find_array_items_matching($incidents,
            ['message_id' => $message->id, 'description' => 'Message sending period is set to an invalid value of 0 ms']));

        $this->messages->delete_forever($message);
    }

    // #95: DRC on Playground package triggers Integrity constraint violation
    public function testUnnamedFieldDoesNotCauseCrash(): void {
        $this->expectNotToPerformAssertions();

        $this->testUtil->initialize_database('Issue95');

        $this->drc->run_for_package(1);
    }

    private function find_array_items_matching(array $array, array $filter): array
    {
        $results = [];

        foreach ($array as $item) {
            foreach ($filter as $key => $value) {
                if ($item->{$key} !== $value)
                    continue 2;
            }

            $results[] = $item;
        }

        return $results;
    }
}
