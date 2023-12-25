INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'MyPackage', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (1, 1, null, 0x100, 'DIRECT', 'Message1', 'Test message', '_db');

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('2', '1', 'ECU2', 'Test unit', '1', '_db', CURRENT_TIMESTAMP(), '1');

INSERT INTO `message` (`id`, `node_id`, `bus_id`, `can_id`, `can_id_type`, `name`, `description`, `who_changed`)
        VALUES (2, 1, null, 0x100, 'DIRECT', 'Message2', 'Test message', '_db');

INSERT INTO `message_node` (`id`, `node_id`, `message_id`, `operation`) VALUES (1, 1, 1, 'RECEIVER');
INSERT INTO `message_node` (`id`, `node_id`, `message_id`, `operation`) VALUES (2, 1, 2, 'RECEIVER');
