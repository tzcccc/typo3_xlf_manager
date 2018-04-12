CREATE TABLE tx_typo3xlfmanager_model_domain_file (

	uid int(11) NOT NULL auto_increment,
	filename      VARCHAR(255)  DEFAULT '',
	xmlcontent    MEDIUMTEXT DEFAULT '',
	is_selected   TINYINT(1) DEFAULT '0',
	is_current     TINYINT(1) DEFAULT '0',

	PRIMARY KEY (uid)
);