-- Table to log processed parts of this changelog.
-- You will like it later. It is not used by application itself.
CREATE TABLE IF NOT EXISTS `about_changelog` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`filename` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	PRIMARY KEY (`id`)
);


--
INSERT INTO `about_changelog`
SET `filename` = '0000-00-00-about_changelog.sql';

