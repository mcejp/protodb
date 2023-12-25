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

namespace candb;

use PDO;
use PDOException;

final class DB
{
    /** @var PDO */
    private $pdo;

    public function execute_file(string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === FALSE)
            throw new \Exception('File not found: ' . $path);

        $this->execute_query($sql);
    }

    public function execute_query(string $sql): void
    {
        if (trim($sql) !== '') {
            $pdo = $this->getPdo();

            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare($sql);
                $statement->execute();
                while ($statement->nextRowset()) {/* https://bugs.php.net/bug.php?id=61613 */};

                // Make sure no transaction is in flight after this
                if ($pdo->inTransaction() && $pdo->commit() === false) {
                    $pdo->rollBack();
                }
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }

    public function getPdo(): \PDO
    {
        if (!$this->pdo) {
            $pdo_dsn = getenv("PROTODB_PDO_DSN");
            $pdo_user = getenv("PROTODB_PDO_USER");
            $pdo_password = getenv("PROTODB_PDO_PASSWORD");
            $pdo_retry = (bool)getenv("PROTODB_PDO_RETRY");

            if ($pdo_dsn === FALSE)
                require_once(__DIR__ . '/../config/db_credentials.php');

            for ($num_retries = 0; $this->pdo === null; $num_retries++) {
                try {
                    $this->pdo = new PDO($pdo_dsn, $pdo_user, $pdo_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                }
                catch (PDOException $ex) {
                    if ($pdo_retry && $num_retries < 30) {
                        sleep(1);
                        continue;
                    }
                    else {
                        die($ex->getMessage());
                    }
                }
            }

            if (!$this->table_exists('package')) {
                $this->execute_file('db_schema/candb.sql');
            }
        }

        return $this->pdo;
    }

    private function table_exists(string $tablename): bool
    {
        $pdo = $this->getPdo();

        $statement = $pdo->prepare("SHOW TABLES LIKE '$tablename'");
        $statement->execute();

        return $statement->rowCount() > 0;
    }
}
