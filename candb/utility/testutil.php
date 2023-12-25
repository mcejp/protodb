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

namespace candb\utility;

use candb\DB;
use candb\model\BusLink;
use candb\model\EnumItem;
use candb\model\Message;
use candb\model\Package;
use candb\model\Unit;
use candb\service\EnumTypesService;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;

final class TestUtil
{
    public const TEST_USERNAME = '_test';

    private static $enum_type_id;
    private static $message_id;
    private static $package_id;
    private static $unit_id;

    /** @var DB */
    private $db;

    /** @var \PDO */
    private $pdo;

    /** @var EnumTypesService */
    private $enum_types;

    /** @var MessagesService */
    private $messages;

    /** @var PackagesService */
    private $packages;

    /** @var UnitsService */
    private $units;

    public function __construct(DB $db,
                                EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        $this->db = $db;
        $this->pdo = $db->getPdo();
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    public function initialize_database(?string $dataset): void
    {
        self::$enum_type_id = null;
        self::$message_id = null;
        self::$package_id = null;
        self::$unit_id = null;

        $this->db->execute_file('db_schema/truncate_tables.sql');

        if ($dataset !== null)
            $this->db->execute_file("tests/data/$dataset.sql");
    }

    public function initialize_database_from_string(string $sql): void
    {
        self::$enum_type_id = null;
        self::$message_id = null;
        self::$package_id = null;
        self::$unit_id = null;

        $this->db->execute_file('db_schema/truncate_tables.sql');
        $this->db->execute_query($sql);
    }

    /** @deprecated Use CommonTest::a_bus_id or hardcode dataset-specific value */
    public function get_some_bus_id(): int
    {
        return 1;
    }

    /** @deprecated Use CommonTest::some_bus_ids or hardcode dataset-specific value */
    public function get_some_bus_ids(): array
    {
        return [1, 2];
    }

    /** @deprecated Use CommonTest::a_message_id or hardcode dataset-specific value */
    public function get_some_message_id(): int
    {
        if (self::$message_id === null) {
            $unit_id = $this->get_some_unit_id();

            $id = $this->messages->insert(new Message(null, $unit_id, $this->get_some_bus_id(), 0x111,
                        'DIRECT', 'ReferenceMessage', 'Reference test message.', 100, 1000),
                        self::TEST_USERNAME);

            $this->units->insert_message_link($unit_id, $id, UnitsService::OPERATION_SENDER);

            self::$message_id = $id;
        }

        return self::$message_id;
    }

    /** @deprecated Use CommonTest::a_package_id or hardcode dataset-specific value */
    public function get_some_package_id(): int
    {
        if (self::$package_id === null) {
            $all_packages = $this->packages->allPackageNames();

            $test_package_id = 999;
            $test_package_name = 'Testing';

            $id = array_search($test_package_name, $all_packages, true);

            if ($id === false) {
                $id = $this->packages->insert(new Package($test_package_id, $test_package_name), self::TEST_USERNAME);

                $this->create_some_buses($id);
            }

            self::$package_id = $id;
        }

        return self::$package_id;
    }

    /** @deprecated Hardcode a dataset-specific value */
    public function get_some_unit_id(): int
    {
        if (self::$unit_id === null) {
            self::$unit_id = $this->insert_new_unit(['name' => 'ReferenceUnit', 'bus_ids' => [1, 2]]);
        }

        return self::$unit_id;
    }

    public function insert_new_unit(array $options): int
    {
        $package_id = $this->get_some_package_id();

        $unit = new Unit(null, $package_id, 'TestUnit' . rand(0, 1000), 'Reference test unit.',
            null, 'Foo', 'Bar');

        if (isset($options['name'])) {
            $unit->name = $options['name'];
        }

        $id = $this->units->insert($unit, self::TEST_USERNAME);

        if (isset($options['bus_ids'])) {
            $this->units->updateBusLinks($id, array_map(
                fn($bus_id) => new BusLink(null, $bus_id, $id, null),
                $options['bus_ids'])
            );
        }

        return $id;
    }

    private function create_some_buses($package_id)
    {
        $pdo = $this->db->getPdo();

        if ($pdo->query('SELECT NULL FROM `bus` LIMIT 1')->rowCount() === 0) {
            $query = $pdo->prepare("INSERT INTO `bus` (`id`, `package_id`, `name`, `bitrate`) VALUES (1, ?, 'CAN1', 1000000)");
            $query->execute([$package_id]);
            $query = $pdo->prepare("INSERT INTO `bus` (`id`, `package_id`, `name`, `bitrate`) VALUES (2, ?, 'CAN2', 500000)");
            $query->execute([$package_id]);
        }
    }
}
