-- SQLite mirror of setup.sql, for the tests that only need somewhere to put
-- rows rather than a real MySQL server. Kept deliberately close to the MySQL
-- schema: same tables, same columns, same seed rows and ids.
--
-- Differences are dialect only:
--   AUTO_INCREMENT      -> INTEGER PRIMARY KEY AUTOINCREMENT (must be INTEGER
--                          to alias the rowid)
--   ENGINE=InnoDB       -> dropped, SQLite has no storage engines
--   separate KEY (...)  -> dropped, an index adds nothing to these tests

DROP TABLE IF EXISTS `main`;

CREATE TABLE `main` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `first_name` varchar(32) DEFAULT NULL,
  `last_name` varchar(32) DEFAULT NULL,
  `age` INTEGER DEFAULT NULL
);

INSERT INTO `main` (`first_name`, `last_name`, `age`)
VALUES
	('Johnny', 'Appleseed', 28),
	('Jenny', 'Appleseed', 31);

DROP TABLE IF EXISTS `join`;

CREATE TABLE `join` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `parent_id` INTEGER DEFAULT NULL,
  `child_name` varchar(32) DEFAULT NULL
);

INSERT INTO `join` (`parent_id`, `child_name`)
VALUES
	(1, 'Peter'),
	(2, 'Sally'),
	(2, 'Chuck');

DROP TABLE IF EXISTS `crud`;

CREATE TABLE `crud` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `first_name` varchar(32) DEFAULT NULL,
  `last_name` varchar(32) DEFAULT NULL,
  `age` INTEGER DEFAULT NULL,
  `is_active` INTEGER DEFAULT 1
);

INSERT INTO `crud` (`first_name`, `last_name`, `age`)
VALUES
	('Johnny', 'Appleseed', 28),
	('Jenny', 'Appleseed', 31);
