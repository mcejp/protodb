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

namespace candb\service;

use candb\DB;
use candb\DBHelpers;
use candb\model\Changelog;

final class ChangelogService
{
    private $pdo;
    private $dbhelpers;

    public function __construct(DB $db, DBHelpers $dbhelpers)
    {
        $this->pdo = $db->getPdo();
        $this->dbhelpers = $dbhelpers;
    }

    public function log(string $table, string $action, int $row_id, string $who_changed): void
    {
        $query = $this->pdo->prepare("INSERT INTO changelog (`table`, `action`, `row`, `who_changed`) VALUES (?, ?, ?, ?)");
        $query->execute([$table, $action, $row_id, $who_changed]);
    }

    public function log_delete(string $table, int $row_id, string $who_changed): void
    {
        $this->log($table, Changelog::DELETE, $row_id, $who_changed);
    }

    public function log_insert(string $table, int $row_id, string $who_changed): void
    {
        $this->log($table, Changelog::INSERT, $row_id, $who_changed);
    }

    public function log_update(string $table, int $row_id, string $who_changed): void
    {
        $this->log($table, Changelog::UPDATE, $row_id, $who_changed);
    }

    public function by_table(string $table): array
    {
        $query = $this->pdo->prepare("SELECT * FROM changelog WHERE `table` = ? ORDER BY when_changed ASC");

        $query->execute([$table]);
        return $this->dbhelpers->fetch_object_array('\candb\model\Changelog', $query);
    }
}
