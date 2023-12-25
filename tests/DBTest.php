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

require_once 'CommonTest.php';

/**
 * @covers \candb\DB
 */
final class DBTest extends CommonTest
{
    /** @var \candb\DB */ private $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->dice->create('\candb\DB');
    }

    public function testExceptionIsThrownOnError(): void {
        $this->expectException('\PDOException');
        $this->db->execute_query('SELECT * FROM nonexistent_table;');
    }
}
