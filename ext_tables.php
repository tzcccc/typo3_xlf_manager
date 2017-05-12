<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {

	/**
	 * Registers a Backend Module
	 */
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'CCCC.' . $_EXTKEY,
		'tools',			// Make module a submodule of 'user'
		'typo3XLFmanager',	// Submodule key
		'top',				// Position

		//list of controller actions
		array(
			'LanguageFiles' => 'index,update,selectFiles,selectCurrentFile,clearLanguageCache',
		),
		array(
			'access' => 'user, group',
			'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_be.xlf',
		)
	);

}
?>