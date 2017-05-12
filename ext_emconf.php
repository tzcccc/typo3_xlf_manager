<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'XLF files manager',
	'description' => 'Tool for managing and creating XLF translation files and helping by translation keys integration',
	'category' => 'templates',
	'author' => 'Tomas Zdrazil',
	'author_email' => 'tz@cccc.de',
	'author_company' => '4c media',
	'state' => 'stable',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0 - 8.7.99'
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);

?>