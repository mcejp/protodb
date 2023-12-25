INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());

INSERT INTO `bus` (`id`, `package_id`, `name`, `bitrate`) VALUES ('1', '1', 'Bus1', '1000000');

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `enum_type` (`id`, `node_id`, `name`, `description`, `who_changed`)
        VALUES ('1', '1', 'Weekdays', 'Test enum.', '_db');

INSERT INTO `enum_item` (`id`, `enum_type_id`, `position`, `name`, `value`, `description`)
        VALUES ('1', '1', '0', 'monday', '0', 'Monday'),
               ('2', '1', '1', 'tuesday', '1', 'Tuesday'),
               ('7', '1', '6', 'sunday', '6', 'Sunday');

INSERT INTO `node_bus` (`id`, `bus_id`, `node_id`) VALUES (1, 1, 1);

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (1, 1, 1, 0, 'UNDEF', 'Status', 'Test message', '_db');

INSERT INTO `message_field` (`id`, `message_id`, `position`, `name`, `description`, `type`, `bit_size`, `array_length`)
		VALUES (1, 1, 0, 'EnumType', '', '1', 1, 1);

INSERT INTO `message_node` (`id`, `node_id`, `message_id`, `operation`) VALUES (1, 1, 1, 'SENDER');
