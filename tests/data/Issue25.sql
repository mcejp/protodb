INSERT INTO `package` (`id`, `name`, `who_changed`, `when_changed`) VALUES ('1', 'MyPackage', '_db', CURRENT_TIMESTAMP());

INSERT INTO `node` (`id`, `package_id`, `name`, `description`, `code_model_version`, `who_changed`, `when_changed`, `valid`)
        VALUES ('1', '1', 'ECU1', 'Test Unit', '1', '_db', CURRENT_TIMESTAMP(), '1');
