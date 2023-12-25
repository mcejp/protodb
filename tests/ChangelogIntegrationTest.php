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

use candb\model\Changelog;

require_once 'DiceTest.php';

/**
 * @covers candb\service\ChangelogService
 */
final class ChangelogIntegrationTest extends CommonTest
{
    private const WHO_CHANGED = \candb\utility\TestUtil::TEST_USERNAME;

    /** @var \candb\service\ChangelogService */
    private $log;

    /** @var \candb\service\MessagesService */
    private $messages;

    /** @var \candb\service\UnitsService */
    private $units;

    public function setUp(): void
    {
        parent::setUp();

        $this->log = $this->dice->create('\candb\service\ChangelogService');
        $this->messages = $this->dice->create('\candb\service\MessagesService');
        $this->units = $this->dice->create('\candb\service\UnitsService');
    }

    private function filter_log($table, $action, int $row, string $who_changed): array
    {
        /** @var Changelog[] $log */ $log = $this->log->by_table($table);

        return array_filter($log, function(\candb\model\Changelog $entry) use ($table, $action, $row, $who_changed) {
            return $entry->table === $table && $entry->action === $action && $entry->row === $row && $entry->who_changed === $who_changed;
        });
    }

    public function testLogMessageInsert(): void {
        $message = $this->create_a_message();
        $message->id = $this->messages->insert($message, self::WHO_CHANGED);

        $this->assertCount(1,
            $this->filter_log(Changelog::TABLE_MESSAGE, Changelog::INSERT, $message->id, self::WHO_CHANGED)
        );
    }

    public function testLogMessageUpdate(): void {
        $message = $this->create_a_message();
        $message->id = $this->messages->insert($message, self::WHO_CHANGED);

        $this->messages->update($message, self::WHO_CHANGED);

        $this->assertCount(1,
            $this->filter_log(Changelog::TABLE_MESSAGE, Changelog::UPDATE, $message->id, self::WHO_CHANGED)
        );
    }

    public function testLogUnitInsert(): void {
        $unit = $this->create_a_unit();
        $unit->id = $this->units->insert($unit, self::WHO_CHANGED);

        $this->assertCount(1,
            $this->filter_log(Changelog::TABLE_UNIT, Changelog::INSERT, $unit->id, self::WHO_CHANGED)
        );
    }

    public function testLogUnitUpdate(): void {
        $unit = $this->create_a_unit();
        $unit->id = $this->units->insert($unit, self::WHO_CHANGED);

        $this->units->update($unit, self::WHO_CHANGED);

        $this->assertCount(1,
            $this->filter_log(Changelog::TABLE_UNIT, Changelog::UPDATE, $unit->id, self::WHO_CHANGED)
        );
    }
}
