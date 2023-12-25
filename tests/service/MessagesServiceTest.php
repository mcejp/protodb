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

use candb\model\Message;

require_once 'tests/DiceTest.php';

/**
 * @covers candb\service\MessagesService
 */
final class MessagesServiceTest extends CommonTest
{
    /** @var \candb\service\MessagesService */
    private $messages;

    public function setUp(): void
    {
        parent::setUp();

        $this->messages = $this->dice->create('\candb\service\MessagesService');
    }

    private static function contains_entity_by_id(array $haystack, int $id): bool
    {
        foreach ($haystack as $unit) {
            if ($unit->id === $id)
                return true;
        }

        return false;
    }

    public function testCanInsertMessage(): void {
        $message = $this->create_a_message();

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);
        $this->assertNotEmpty($message->id);
    }

    public function testBuses(): void
    {
        // Test insert
        $message = $this->create_a_message();
        $message->set_buses([1, 2]);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);
        $this->assertNotEmpty($message->id);

        $message = $this->messages->byId($message->id, with_buses: true);
        $this->assertEquals([1, 2], $message->get_buses());

        // Test search by additional ID
        $this->assertEquals([$message->id], $this->messages->get_ids_associated_to_bus(2));
        $this->assertEquals([], $this->messages->get_ids_associated_to_bus(3));

        // Test update
        $message->set_buses([2, 3]);
        $this->messages->update($message, \candb\utility\TestUtil::TEST_USERNAME);

        $message = $this->messages->byId($message->id, with_buses: true);
        $this->assertEquals([2, 3], $message->get_buses());

        // Test search by additional ID
        $this->assertEquals([$message->id], $this->messages->get_ids_associated_to_bus(3));
    }

    public function testEmptyNameIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);

        $message = $this->create_a_message();
        $message->name = '';

        $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);
    }

    public function testInvalidatedMessagesDontShowUp(): void
    {
        // Create a new message and assign it to some bus
        $message = $this->create_a_message();
        $message->bus_id = $this->a_bus_id;
        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        // Make sure it shows up when the bus is queried for all messages
        $results = $this->messages->by_bus_id($this->a_bus_id);
        $this->assertTrue($this->contains_entity_by_id($results, $message->id));

        // Soft-delete the message
        $this->messages->delete($message, \candb\utility\TestUtil::TEST_USERNAME);

        // Make sure it doesn't show up anymore
        $results = $this->messages->by_bus_id($this->a_bus_id);
        $this->assertFalse($this->contains_entity_by_id($results, $message->id));
    }

    public function testMessageWithNullBusIdIsRetrievedCorrectly(): void {
        $message = $this->create_a_message();
        $this->assertSame(null, $message->bus_id);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        $message = $this->messages->byId($message->id);
        $this->assertSame(null, $message->bus_id);
    }

    public function testMessageNullCanIdRoundTrips(): void {
        $message = $this->create_a_message();
        $message->set_can_id(Message::CAN_ID_TYPE_UNDEF, null);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        $message = $this->messages->byId($message->id);
        $this->assertSame(null, $message->get_can_id());
    }

    public function testMessageCanIdCoercedToNullIfUndefined(): void {
        $message = $this->create_a_message();
        $message->set_can_id(Message::CAN_ID_TYPE_UNDEF, 666);

        $message->id = $this->messages->insert($message, \candb\utility\TestUtil::TEST_USERNAME);

        $message = $this->messages->byId($message->id);
        $this->assertSame(null, $message->get_can_id());
    }
}
