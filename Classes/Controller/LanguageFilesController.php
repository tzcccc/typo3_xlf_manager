<?php
namespace CCCC\Typo3XlfManager\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
use \TYPO3\CMS\Core\Messaging\FlashMessage;
use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class LanguageFilesController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	const EXTKEY = 'typo3_xlf_manager';

	/*
     * default language fallback if not set in settings
     */
	protected $defaultLanguage = 'en';

	/**
	 * @var array additional languages and flags
	 */
	protected $systemLanguages = array();
	protected $flagsByISOcode = array();

	/**
	 * @var array extension settings
	 */
	protected $extensionSettings = array();

	/**
	 * @var path to TYPO3 language cache folder
	 */
	protected $languageCacheFolder;

    /**
     * @var TYPO3 version
     */
	protected $typo3Version;

    public function initializeAction(){

        //determine typo3 version
        $this->typo3Version = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionStringToArray(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version());

    	$thisExtensionFolderPath = ExtensionManagementUtility::extPath(self::EXTKEY);

        //initialize paths for configuration files and cache folder
		$configurationFolder = $thisExtensionFolderPath.'Configuration/';
        $extPathSplit = explode('typo3conf',$thisExtensionFolderPath);
        //setup language cache folder
        $this->languageCacheFolder = $extPathSplit[0].'typo3temp/var/Cache/Data/l10n/';
        if($this->typo3Version['version_main'] < 8){
            $this->languageCacheFolder = $extPathSplit[0].'typo3temp/Cache/Data/l10n/';
        }

        //initialize language files configuration
		$this->configurationFile = $configurationFolder.'languageFiles.json';

        //initialize system languages and flags
        $sql = "SELECT language_isocode,flag FROM sys_language WHERE hidden = 0";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
            $this->systemLanguages[$row['flag']] = $row['language_isocode'];
            $this->flagsByISOcode[$row['language_isocode']] = $row['flag'];
        }
        //initialize default language from extension settings
        $this->extensionSettings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTKEY]);
        if($this->extensionSettings['defaultLanguage'] && strlen($this->extensionSettings['defaultLanguage']) == 2){
            $this->defaultLanguage = $this->extensionSettings['defaultLanguage'];
        }

        //remove default langauge from languages array
        foreach($this->systemLanguages as $lKey => $l){
            if($l == $this->defaultLanguage){
                unset($this->systemLanguages[$lKey]);
            }
        }

        //add flag for default language
		$this->flagsByISOcode[$this->defaultLanguage] = $this->defaultLanguage;
    }

    public function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view) {


		$this->view->assign('typo3Version',$this->typo3Version);

    	//assign css and js file for every action
		$this->view->assign('cssFile',ExtensionManagementUtility::extRelPath(self::EXTKEY).'Resources/Public/css/styles.css');
		$this->view->assign('jsFile',ExtensionManagementUtility::extRelPath(self::EXTKEY).'Resources/Public/js/script.js');
		$variablesToShowCount = 0;
		foreach($this->extensionSettings as $k => $s){
			if(substr_count($k,'showVariable') && $s){
				$variablesToShowCount++;
			}
		}
		//calculate focus offset update depending on count of variables to show
		$variablesFocusPixelsToSubtract = ($variablesToShowCount * 38 / 2) - 17;

		//assign view variables
		$this->view->assign('extensionSettings',$this->extensionSettings);
		$this->view->assign('variablesToShowCount',$variablesToShowCount);
		$this->view->assign('pixelToSubtract',$variablesFocusPixelsToSubtract);
		$this->view->assign('defaultLanguage',$this->defaultLanguage);
		$this->view->assign('typo3SitePath',\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_PATH'));
	}

    /**
	 * action index
	 *
	 * @return void
	 */
	public function indexAction() {

		//check default language and locales
		$locales = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Localization\\Locales');
		$localesLanguages = $locales->getLocales();
		if($this->defaultLanguage != 'en' && !in_array('en',$localesLanguages)){
			$this->addFlashMessage('If you want to use default language other than "English" you need to extend locales language list by "en" => "English" in TYPO3\CMS\Core\Localization\Locales::$languages to be able to use "en.locallang.xlf" files.','LOCALES ADJUSTMENT NEEDED!',FlashMessage::ERROR);
		}

		$selectedFiles = $this->getSelectedFiles();
		$currentFile = $this->getCurrentFile();

		//check if files assigned
		if(!empty($selectedFiles)){
			$this->view->assign('filesAssigned',true);
		}

		if($currentFile && is_file($currentFile)){

			$fileData = $this->getExtensionAndFilenameFormPath($currentFile);

			$fileName = $fileData['filename'];

			$DATA = array();

			$xml = simplexml_load_file($currentFile);

			$elements = $xml->xpath('//trans-unit');
			if($elements){
				foreach($elements as $e){
					$DATA[$fileName][$this->defaultLanguage][$e->attributes()->id->__toString()] = array(
						'source' => $e->source->__toString()
					);
				}
			} else {
				$DATA[$fileName][$this->defaultLanguage] = array(); //if default file empty
			}


			foreach($this->systemLanguages as $lang){
				$langFileName = $fileData['folder'].$lang.'.'.$fileName;
				$DATA[$fileName][$lang] = array();
				if(is_file($langFileName)) {
					$langXml = simplexml_load_file($langFileName);
					$langElements = $langXml->xpath('//trans-unit');
					foreach ($langElements as $e) {
						$DATA[$fileName][$lang][$e->attributes()->id->__toString()] = array(
							'target' => $e->target->__toString()
						);
					}
				}
				$DATA[$fileName][$lang] = array_merge_recursive($DATA[$fileName][$this->defaultLanguage],$DATA[$fileName][$lang]);
			}


			//sort ids by name
			foreach($DATA as $fileName => $l){
				foreach($l as $lang => $row){

					ksort($DATA[$fileName][$lang]);
					//add category and subcategory markers
					$firstId = array_keys($DATA[$fileName][$lang]);
					$firstIdSplit = explode('.',$firstId[0]);
					$cat = $firstIdSplit[0];
					$subcat = $firstIdSplit[1];
					$isFirst = true;
					foreach($DATA[$fileName][$lang] as $id => $value){
						//add flag
						$DATA[$fileName][$lang][$id]['flag'] = $this->flagsByISOcode[$lang];
						//explode id
						$cats = explode('.',$id);
						if($cat != $cats[0] || $isFirst){
							if($cat){
								$DATA[$fileName][$lang][$id]['cat'] = $cats[0];
							}
							$cat = $cats[0];
						}

						if($cats[1] && $subcat != $cats[1] || $isFirst){
							$DATA[$fileName][$lang][$id]['subcat'] = '<span class="catprefix">'.$cats[0].'.</span>'.$cats[1];
							$DATA[$fileName][$lang][$id]['subcatAsId'] = $cats[0].$cats[1];
							$subcat = $cats[1];
						}
						$isFirst = false;
					}
				}

				//input fields limit check
				$inputFieldsCount = count($DATA[$fileName][$this->defaultLanguage]) * (count($this->systemLanguages)+1);
				$maxInputFields = (int)ini_get('max_input_vars');
				if($inputFieldsCount >= $maxInputFields){
					$this->addFlashMessage('Your field count: '.$inputFieldsCount.'<br>PHP ini "max_input_vars": '.$maxInputFields,'INCREASE INPUT FIELDS LIMIT',FlashMessage::ERROR);
				} else if(($inputFieldsCount + 50) >= $maxInputFields){
					$this->addFlashMessage('Your field count: '.$inputFieldsCount.'<br>PHP ini "max_input_vars": '.$maxInputFields,'INCREASE INPUT FIELDS LIMIT',FlashMessage::WARNING);
				}

			}
			$this->view->assign('data',$DATA);
			$this->view->assign('currentFileName',$fileName);
			$this->view->assign('extension',$fileData['extension']);
			$this->view->assign('fileFolder',$fileData['folder']);
		}


        $this->systemLanguages = array($this->defaultLanguage => $this->defaultLanguage) + $this->systemLanguages;

        $this->view->assign('languages',$this->systemLanguages);
        $this->view->assign('currentFile',$currentFile);

	}

    public function updateAction() {

	    $fileToUpdate = $_POST['currentFile'];

    	if($fileToUpdate){

    		//get data from filepath
    		$fileData = $this->getExtensionAndFilenameFormPath($fileToUpdate);

			$xliff = $_POST['xliff'];

			$DATA = array();

			//parse posted data
			foreach ($xliff as $key => $val) {
				$s = explode('__', $key);
				//filter out empty language key
				if($s[0]){
					$DATA[$s[0]][$s[1]][$s[2]] = trim($val);
				}
			}

			array_unshift($this->systemLanguages,$this->defaultLanguage);


			$messages = array();
			//save as xml to .xlf
			foreach ($DATA as $fileName => $file) {

			    $xmlDataForAllLanguages = [];
				foreach ($this->systemLanguages as $lang) {

					$xmlString = $this->getXLIFFDefaultXML($lang);

					$xml = simplexml_load_string($xmlString);
					$xpath = $xml->xpath('//body');
					$body = $xpath[0];

					foreach ($file[$lang] as $id => $unit) {
						$sxe = $body->addChild('trans-unit');
						$sxe->addAttribute('id', $id);
						$sxe->addAttribute('approved', 'yes');
						$sxe->addAttribute('xmlns:xml:space', 'preserve');
						$sxe->addChild('source', stripcslashes($DATA[$fileName][$this->defaultLanguage][$id]));
						if ($unit) {
							if($lang != $this->defaultLanguage) {
								$sxe->addChild('target', stripcslashes($unit));
							}
						}
					}
					$xmlStringToSave = $xml->asXML();
					//add tabs for code readability
					$xmlStringToSave = str_replace('<trans-unit', "\n\t\t" . '<trans-unit', $xmlStringToSave);
					$xmlStringToSave = str_replace('</trans-unit>', "\n\t\t" . '</trans-unit>', $xmlStringToSave);
					$xmlStringToSave = str_replace('<source', "\n\t\t\t" . '<source', $xmlStringToSave);
					$xmlStringToSave = str_replace('<target', "\n\t\t\t" . '<target', $xmlStringToSave);
					$xmlStringToSave = str_replace('</body>', "\n\t\t" . '</body>', $xmlStringToSave);

					$langPrefix = '';
					if($lang != $this->defaultLanguage){
						$langPrefix = $lang.'.';
					}
					$test = file_put_contents($fileData['folder'].$langPrefix.$fileData['filename'],$xmlStringToSave);
					if($test){
					    $xmlDataForAllLanguages[$langPrefix] = $xmlStringToSave;
						$messages[] = $langPrefix.$fileData['filename'];
					}
				}
                $this->backupFileContents($fileToUpdate, serialize($xmlDataForAllLanguages));
			}

			if(!empty($messages)){
				$this->addFlashMessage('','XLF FILES SAVED');
				$this->addFlashMessage(implode('<br />',$messages),'', FlashMessage::INFO);
				//clear language cache
				if($this->extensionSettings['autoClearLangCacheBySave']){
					$this->clearLanguageCache();
				}
			}
		}
		//no current language file selected
		else {
			$this->addFlashMessage('No langauge file selected as current','SAVING XLF FILES', FlashMessage::WARNING);
		}

        $this->redirect('index');
    }

    public function selectFilesAction(){

		//update selected files for translations
	    if($this->request->hasArgument('extensions')){
	        $extensionsPosted = $this->request->getArgument('extensions');

			$localDriver = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\Core\\Resource\\Driver\\LocalDriver');

	        $languageFiles = array();
	        //loop all loaded posted extensions
	        foreach($extensionsPosted as $extKey => $ext){

	        	//continue if extension not currently loaded -- should not happen due previous check in extension listing
                if(!ExtensionManagementUtility::isLoaded($extKey)){
                    continue;
                }
	            $extPath = ExtensionManagementUtility::extPath($extKey);
	            //check if extension path exists
	            if(is_dir($extPath)){
	                $extLangPath = $extPath.'Resources/Private/Language/';
	                //check if new file should be created
	                if($ext['cccc']){
	                	//create folder if not extists
						if(!is_dir($extLangPath)){
							mkdir($extLangPath);
						}
	                	//prepare filename
	                	$newFilename = $localDriver->sanitizeFileName($ext['cccc']);
	                	if($newFilename){
							$newFilePath = $extLangPath.'locallang_'.$newFilename.'.xlf';
							//create cccc file if not exists
							if(!is_file($newFilePath)){
								file_put_contents($newFilePath,$this->getXLIFFDefaultXML());
							}
							$languageFiles[] = $newFilePath;
						} else {
							$this->addFlashMessage($ext['cccc'].' not created','CONFIGURATION SAVED',FlashMessage::ERROR);
						}
                    }
                    //check for files
                    if(!empty($ext['files'])){
	                    foreach($ext['files'] as $f){
	                        $langFile = $extLangPath.$f;
	                        if(is_file($langFile)){
	                            $languageFiles[] = $langFile;
                            }
                        }
                    }
                }
            }

            //save selected files to configuration file
            if(!empty($languageFiles)){

	        	//save to database
                $save = $this->setSelectedFiles($languageFiles);
	            if($save){
					$this->addFlashMessage('','CONFIGURATION SAVED');
					$this->addFlashMessage(implode('<br>',array_map(function($a){
						$fileData = $this->getExtensionAndFilenameFormPath($a);
						if(!empty($fileData)){
							$strToReturn = '<strong>'.$fileData['extension'].'</strong> | '.$fileData['filename'];
							return $strToReturn;
						}
					},$languageFiles)),'',FlashMessage::INFO);
					$this->redirect('index');
				}
            }
        }

        //get all extensions in typo3conf/ext folder
	    $extensionsList = glob(ExtensionManagementUtility::extPath(self::EXTKEY).'../*',GLOB_ONLYDIR);
	    $extensions = array();

	    //loop all extension folders
	    foreach($extensionsList as $e){
	        $extKey = basename($e);
	        //continue if extension not loaded
            if(!ExtensionManagementUtility::isLoaded($extKey)){
                continue;
            }
	        $ext = array('ext' => $extKey);
            //get all xlf-files in Lanuguage directory
	        $existingFiles = glob($e.'/Resources/Private/Language/*.xlf');
	        //assign checked states
	        if(!empty($existingFiles)){
	            $ext['files'] = array();
                foreach($existingFiles as $eF){
                    $clearedName = str_replace(self::EXTKEY.'/../','',$eF);
                    $basename = basename($eF);
                    if(substr($basename,2,1) != '.'){
                        $ext['files'][] = array('filename' => $basename,'checked' => in_array($clearedName,$this->getSelectedFiles()));
                    }
                    if($basename == 'locallang_cccc.xlf'){
                        $ext['checked'] = true;
                    }
                };
            }
            $extensions[] = $ext;
        }

        //assign data to view
        $this->view->assign('extensions',$extensions);

    }

	public function selectCurrentFileAction(){

		//update current file for translations
		if($this->request->hasArgument('currentFile')) {
			$currentFile = $this->request->getArgument('currentFile');

			//assign current file for translation and redirect to index
			if($currentFile){

				$saved = $this->setCurrentFile($currentFile);
				if($saved) {
					$this->addFlashMessage('current translation file assigned','CONFIGURATION SAVED');
					$this->redirect('index');
				}
			}
		}

		$options = array();

		//prepare options to select
		foreach($this->getSelectedFiles() as $slf){
			$checked = false;
			if($slf == $this->getCurrentFile()){
				$checked = true;
			}
			$fileData = $this->getExtensionAndFilenameFormPath($slf);
			if(!empty($fileData)){
				$options[$fileData['extension']][] = array('fullPath' => $slf,'filename' => $fileData['filename'], 'checked' => $checked);
			}

		}

		$this->view->assign('data',$options);
	}

	/**
	 * Clear language cache action
	 */
	public function clearLanguageCacheAction(){
		$this->clearLanguageCache();
		$this->redirect('index');
	}

    /**
     * Save xlf file content to database for backup purpose
     * @param $file
     * @param $serializedXmlContent
	 * @return boolean
     */
	protected function backupFileContents($file, $serializedXmlContent){
		return $GLOBALS['TYPO3_DB']->exec_UPDATEquery( 'tx_typo3xlfmanager_model_domain_file'
			, "filename='".$file."'"
			, array('xmlcontent' => $serializedXmlContent)
		);
    }

    /**
     * Get array of selected files
     * @return array
     */
	protected function getSelectedFiles(){
        $selectedFiles = array();
        $sql = "SELECT filename FROM tx_typo3xlfmanager_model_domain_file WHERE is_selected = 1";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
            $selectedFiles[] = $row['filename'];
        }
        return $selectedFiles;
    }

    /**
     * Save selected flag to files - insert if not in database
     * @param $files
     * @return bool
     */
    protected function setSelectedFiles($files){
        //set all file paths to not selected
        $sql = "UPDATE tx_typo3xlfmanager_model_domain_file SET is_selected = 0";
        $GLOBALS['TYPO3_DB']->sql_query($sql);


        //get all file paths from database
        $sql = "SELECT filename FROM tx_typo3xlfmanager_model_domain_file";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        $allFilesInDB = array();
        while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
            $allFilesInDB[] = $row['filename'];
        }

        $allSaved = true;
        foreach($files as $lf){
            if(!in_array($lf,$allFilesInDB)){
                $sql = "INSERT INTO tx_typo3xlfmanager_model_domain_file SET filename='".$lf."', is_selected = 1";
            } else {
                $sql = "UPDATE tx_typo3xlfmanager_model_domain_file SET is_selected = 1 WHERE filename='".$lf."'";
            }
            $save = $GLOBALS['TYPO3_DB']->sql_query($sql);
            if(!$save){
                $allSaved = false;
            }
        }
        return $allSaved;
    }

    /**
     * Get current file
     * @return mixed
     */
    protected function getCurrentFile(){
        $sql = "SELECT filename FROM tx_typo3xlfmanager_model_domain_file WHERE is_current = 1";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
        return $row['filename'];
    }

    /**
     * Flag file as current
     * @param $file
     * @return mixed
     */
    protected function setCurrentFile($file){
        //set previous current to not current
        $sql = "UPDATE tx_typo3xlfmanager_model_domain_file SET is_current = 0 WHERE is_current = 1";
        $GLOBALS['TYPO3_DB']->sql_query($sql);
        //set new current
        $sql = "UPDATE tx_typo3xlfmanager_model_domain_file SET is_current = 1 WHERE filename = '".$file."'";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        return $res;
    }

	/**
	 * Get extension name and file name from language file path
	 * @param string $filePath
	 * @return array
	 */
	protected function getExtensionAndFilenameFormPath($filePath){
		if(is_file($filePath)){
			$pathSplit = explode('typo3conf/ext/',$filePath);
			if($pathSplit[1]){
				$restSplit = explode('/', $pathSplit[1]);
				if($restSplit[0]){
					$basename = basename($filePath);
					return array('extension' => $restSplit[0],'filename' => $basename,'folder' => str_ireplace($basename,'',$filePath));
				}
			}
		}
		return array();
	}

    /**
     * Get default XLIFF xml code
     *
     * @param string $lang
     * @return string
     */
    protected function getXLIFFDefaultXML($lang = ''){
        $targetLang = null;
        if($lang && $lang != $this->defaultLanguage){
            $targetLang = $lang;
        }
        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="' . $this->defaultLanguage . '"'.($targetLang ? ' target-language="'.$targetLang.'"' : '').' datatype="plaintext">
        <body></body>
    </file>
</xliff>';
    }

	/**
	 * Clear language cache
	 */
    protected function clearLanguageCache(){
    	$deletedFiles = 0;
		$langCache = glob($this->languageCacheFolder.'*');
		foreach($langCache as $lcf){
			if(is_file($lcf)){
				$success = unlink($lcf);
				if($success){
					$deletedFiles++;
				}
			}
		}
		if($deletedFiles){
			$this->addFlashMessage('','LANGUAGE CACHE CLEARED', FlashMessage::INFO);
		}
	}

	/**
	 * Creates a Message object and adds it to the FlashMessageQueue.
	 *
	 * @param string $messageBody The message
	 * @param string $messageTitle Optional message title
	 * @param int $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
	 * @param bool $storeInSession Optional, defines whether the message should be stored in the session (default) or not
	 * @throws \InvalidArgumentException if the message body is no string
	 * @see \TYPO3\CMS\Core\Messaging\FlashMessage
	 * @api
	 */
	public function addFlashMessage($messageBody, $messageTitle = '', $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, $storeInSession = true)
	{
		if (!is_string($messageBody)) {
			throw new \InvalidArgumentException('The message body must be of type string, "' . gettype($messageBody) . '" given.', 1243258395);
		}

		//remove HTML-tags temporarily until flash message rendering supports them
		$messageBody = strip_tags(str_replace('<br>',' ',$messageBody));

		/* @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
		$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			\TYPO3\CMS\Core\Messaging\FlashMessage::class,
			(string)$messageBody,
			(string)$messageTitle,
			$severity,
			$storeInSession
		);
		$this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
	}

}
?>