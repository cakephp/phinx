CREATE TABLE `phinxlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(14) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;
