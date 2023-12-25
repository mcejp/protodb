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
 * @covers candb\service\UnitsService
 */
final class UnitsServiceTest extends CommonTest
{
    /** @var \candb\service\UnitsService */
    private $units;

    public function setUp(): void
    {
        parent::setUp();

        $this->units = $this->dice->create('\candb\service\UnitsService');
    }

    private static function contains_node_message_link(array $haystack, int $node_id, int $message_id, string $operation): bool
    {
        foreach ($haystack as $link) {
            if ($link["node_id"] === $node_id && $link["message_id"] === $message_id && $link["operation"] === $operation) {
                return true;
            }
        }

        return false;
    }

    private function insertNewUnit(): \candb\model\Unit
    {
        $unit = $this->create_a_unit();

        $unit->id = $this->units->insert($unit, \candb\utility\TestUtil::TEST_USERNAME);
        return $unit;
    }

    public function testBasicOperationsWork(): void {
        $this->assertNotEmpty($this->units->allUnitNames());
    }

    public function testCanInsertUnit(): void {
        $unit = $this->insertNewUnit();
        $this->assertNotEmpty($unit->id);

        $this->units->updateBusLinks($unit->id, [
            new \candb\model\BusLink(null, 1, $unit->id, 'Note A'),
            new \candb\model\BusLink(null, 2, $unit->id, 'Note B'),
        ]);

        $unit = $this->units->byId($unit->id, true);
        $this->assertNotCount(0, $unit->bus_links);

        foreach ($unit->bus_links as $bus_link)
            $this->assertEquals($bus_link->node_id, $unit->id);

        $this->units->delete_forever($unit);
    }

    public function testUnitCodeModelVersionIsCorrectType(): void {
        $unit = $this->units->byId($this->test_util->get_some_unit_id());

        $this->assertTrue(is_int($unit->code_model_version));
    }

    public function testCanSetUnitCodeModelVersionOnInsertion(): void {
        $unit = $this->create_a_unit();

        $unit->code_model_version = 17;

        $unit->id = $this->units->insert($unit, \candb\utility\TestUtil::TEST_USERNAME);

        $unit2 = $this->units->byId($unit->id);

        $this->assertEquals($unit->code_model_version, $unit2->code_model_version);

        $this->units->delete_forever($unit);
    }

    public function testCanSetUnitCodeModelVersionOnUpdate(): void {
        $unit = $this->insertNewUnit();

        $unit->code_model_version = 17;

        $this->units->update($unit, \candb\utility\TestUtil::TEST_USERNAME);

        $unit2 = $this->units->byId($unit->id);

        $this->assertEquals($unit->code_model_version, $unit2->code_model_version);

        $this->units->delete_forever($unit);
    }

    /**
     * #90: PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '...' for key 'node_id'
     */
    public function testMessageLinksCanBeUpdatedWithoutCrashing(): void {
        $unit = $this->units->byId(1, false, true);
        $this->assertTrue(self::contains_node_message_link($unit->sent_messages, 1, 1, 'SENDER'));

        // Dataset contains a MessageLink(node_id=1, message_id=1, SENDER)
        // The bug is triggered by deleting such link and inserting an equivalent one in the web UI,
        // because DBHelpers::update_list would execute the INSERT before DELETE
        $this->units->updateMessageLinks(1, [['id' => null, 'message_id' => 1]], []);
    }

    public function testMessageLinksCanBeUpdatedWithoutCrashing2(): void {
        $this->markTestSkipped("Known to be broken");

        // Test atomicity of UPDATE
        // First prepare 2 links
        $this->units->updateMessageLinks(1, [
            ['id' => null, 'message_id' => 1],
            ['id' => null, 'message_id' => 2]], []);
        $unit = $this->units->byId(1, false, true);
        $sent = $unit->sent_messages;

        // Swap message IDs in the links, but preserve link IDs, so that they will be UPDATEd
        [$sent[0]["message_id"], $sent[1]["message_id"]] = [$sent[1]["message_id"], $sent[0]["message_id"]];
        $this->units->updateMessageLinks(1, $sent, []);
    }
}
