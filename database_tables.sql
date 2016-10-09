DROP TABLE IF EXISTS `bw_comics`;
CREATE TABLE IF NOT EXISTS `bw_comics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(75) NOT NULL,
  `link` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `bw_comic_data`;
CREATE TABLE IF NOT EXISTS `bw_comic_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(10) NOT NULL,
  `date` varchar(10) NOT NULL,
  `title` varchar(75) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
