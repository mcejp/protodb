INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'TestPackage1', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', '', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (1, 1, NULL, 0, 'UNDEF', 'Status', '', '_db');

INSERT INTO `message_field` (`id`, `message_id`, `position`, `name`, `description`, `type`, `bit_size`, `array_length`)
		VALUES (1, 1, 0, '', '', 'bool', 1, 1);
