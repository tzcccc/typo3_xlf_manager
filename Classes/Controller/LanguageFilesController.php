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
class LanguageFilesController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	const EXTKEY = 'typo3_xlf_manager';

	/*
     * default language fallback if not set in settings
     */
	protected $defaultLanguage = 'en';

	/**
	 * @var array additional languages
	 */
	protected $systemLanguages = array();

	/**
	 * configuration files
	 */
	protected $selectedLanguageFilesConfigurationFile;
	protected $currentLanguageFile;

	/**
	 * @var array extension settings
	 */
	protected $extensionSettings = array();

	/**
	 * path to TYPO3 language cache folder
	 */
	protected $languageCacheFolder;

    public function initializeAction(){

    	$thisExtensionFolderPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::EXTKEY);

        //initialize paths for configuration files and cache folder
		$this->configurationFolder = $thisExtensionFolderPath.'Configuration/';
        $this->selectedLanguageFilesConfigurationFile = $this->configurationFolder.'selectedLanguageFiles.txt';
        $this->currentLanguageFile = $this->configurationFolder.'currentLanguageFile.txt';
        $extPathSplit = explode('typo3conf',$thisExtensionFolderPath);
		$this->languageCacheFolder = $extPathSplit[0].'typo3temp/Cache/Data/l10n/';

        //initialize system languages
        $sql = "SELECT static_lang_isocode,flag FROM sys_language WHERE hidden = 0";
        $res = $GLOBALS['TYPO3_DB']->sql_query($sql);
        while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
            $this->systemLanguages[$row['flag']] = $row['static_lang_isocode'];
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
    }

    public function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view) {
    	//assign css and js file for every action
		$this->view->assign('cssFile',\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath(self::EXTKEY).'Resources/Public/css/styles.css');
		$this->view->assign('jsFile',\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath(self::EXTKEY).'Resources/Public/js/script.js');
		$variablesToShowCount = 0;
		foreach($this->extensionSettings as $k => $s){
			if(substr_count($k,'showVariable') && $s){
				$variablesToShowCount++;
			}
		}
		//calculate focus offset update depending on count of variables to show
		$variablesFocusPixelsToSubtract = (($variablesToShowCount - 1) * 38 / 2) - 17;

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
			$this->addFlashMessage('If you want to use default language other than "English" you need to extend locales language list by "en" => "English" in TYPO3\CMS\Core\Localization\Locales::$languages to be able to use "en.locallang.xlf" files.','LOCALES ADJUSTMENT NEEDED!','ERROR');
		}

		//check if files assigned
		$selectedLanguageFiles = $this->getSelectedFilesFromConfiguration();
		if(!empty($selectedLanguageFiles)){
			$this->view->assign('filesAssigned',true);
		}

		$currentTranslationFile = $this->getCurrentLanguageFile();
		if($currentTranslationFile){

			$fileData = $this->getExtensionAndFilenameFormPath($currentTranslationFile);

			$fileName = $fileData['filename'];

			$DATA = array();

			$xml = simplexml_load_file($currentTranslationFile);

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

			/* echo '<pre>';
					 var_dump($DATA);
			 echo '</pre>';*/



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
					$this->addFlashMessage('Your field count: '.$inputFieldsCount.'<br>PHP ini "max_input_vars": '.$maxInputFields,'INCREASE INPUT FIELDS LIMIT','ERROR');
				} else if(($inputFieldsCount + 50) >= $maxInputFields){
					$this->addFlashMessage('Your field count: '.$inputFieldsCount.'<br>PHP ini "max_input_vars": '.$maxInputFields,'INCREASE INPUT FIELDS LIMIT','WARNING');
				}

			}
			$this->view->assign('data',$DATA);
			$this->view->assign('currentFileName',$fileName);
			$this->view->assign('extension',$fileData['extension']);
		}


        $this->systemLanguages = array($this->defaultLanguage => $this->defaultLanguage) + $this->systemLanguages;

        $this->view->assign('languages',$this->systemLanguages);

	}

    public function updateAction()
    {

    	$currentLanguageFile = $this->getCurrentLanguageFile();
    	if($currentLanguageFile){

    		//get data from filepath
    		$fileData = $this->getExtensionAndFilenameFormPath($currentLanguageFile);

			$xliff = $_POST['xliff'];

			$DATA = array();

			//parse posted data
			foreach ($xliff as $key => $val) {
				$s = explode('__', $key);
				$DATA[$s[0]][$s[1]][$s[2]] = trim($val);
			}

			array_unshift($this->systemLanguages,$this->defaultLanguage);


			$messages = array();
			//save as xml to .xlf
			foreach ($DATA as $fileName => $file) {
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
						$messages[] = $langPrefix.$fileData['filename'];
					}
				}
			}

			if(!empty($messages)){
				$this->addFlashMessage('','XLF FILES SAVED');
				$this->addFlashMessage(implode('<br />',$messages),'', 'INFO');
				//clear language cache
				if($this->extensionSettings['autoClearLangCacheBySave']){
					$this->clearLanguageCache();
				}
			}
		}
		//no current language file selected
		else {
			$this->addFlashMessage('No langauge file selected as current','SAVING XLF FILES', 'WARNING');
		}

        $this->redirect('index');
    }

    public function selectFilesAction(){

		//update selected files for translations
	    if($this->request->hasArgument('extensions')){
	        $extensionsPosted = $this->request->getArgument('extensions');

			$localDriver = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\Core\\Resource\\Driver\\LocalDriver');

	        $langaugeFiles = array();
	        //loop all loaded posted extensions
	        foreach($extensionsPosted as $extKey => $ext){

	        	//continue if extension not currently loaded -- should not happen due previous check in extension listing
                if(!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey)){
                    continue;
                }
	            $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extKey);
	            //check if extension path exists
	            if(is_dir($extPath)){
	                $extLangPath = $extPath.'Resources/Private/Language/';
	                //check if new file should be created
	                if($ext['cccc']){
	                	//prepare filename
	                	$newFilename = $localDriver->sanitizeFileName($ext['cccc']);
	                	if($newFilename){
							$newFilePath = $extLangPath.'locallang_'.$newFilename.'.xlf';
							//create cccc file if not exists
							if(!is_file($newFilePath)){
								file_put_contents($newFilePath,$this->getXLIFFDefaultXML());
							}
							$langaugeFiles[] = $newFilePath;
						} else {
							$this->addFlashMessage($ext['cccc'].' not created','CONFIGURATION SAVED','ERROR');
						}
                    }
                    //check for files
                    if(!empty($ext['files'])){
	                    foreach($ext['files'] as $f){
	                        $langFile = $extLangPath.$f;
	                        if(is_file($langFile)){
	                            $langaugeFiles[] = $langFile;
                            }
                        }
                    }
                }
            }

            //save selected files to configuration file
            if(!empty($langaugeFiles)){
	            file_put_contents($this->selectedLanguageFilesConfigurationFile,implode("\n",$langaugeFiles));
	            $this->addFlashMessage('','CONFIGURATION SAVED');
	            $this->addFlashMessage(implode('<br>',array_map(function($a){
	            	$fileData = $this->getExtensionAndFilenameFormPath($a);
	            	if(!empty($fileData)){
	            		$strToReturn = '<strong>'.$fileData['extension'].'</strong> | '.$fileData['filename'];
	            		return $strToReturn;
					}
				},$langaugeFiles)),'','INFO');
	            $this->redirect('index');
            }
        }

        //get selected files from configuration file
        $selectedLanguageFiles = $this->getSelectedFilesFromConfiguration();

        //get all extensions in typo3conf/ext folder
	    $extensionsList = glob(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::EXTKEY).'../*',GLOB_ONLYDIR);
	    $extensions = array();

	    //loop all extension folders
	    foreach($extensionsList as $e){
	        $extKey = basename($e);
	        //continue if extension not loaded
            if(!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey)){
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
                        $ext['files'][] = array('filename' => $basename,'checked' => in_array($clearedName,$selectedLanguageFiles));
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

		$selectedLanguageFiles = $this->getSelectedFilesFromConfiguration();

		//update current file for translations
		if($this->request->hasArgument('currentFile')) {
			$currentFile = $this->request->getArgument('currentFile');

			//save current file for translation and redirect to index
			if($currentFile){
				$test = file_put_contents($this->currentLanguageFile,$currentFile);
				if($test) {
					$this->addFlashMessage('current translation file assigned','CONFIGURATION SAVED');
					$this->redirect('index');
				}
			}
		}

		//get current language file
		$currentLanguageFile = $this->getCurrentLanguageFile();

		$options = array();

		//prepare options to select
		foreach($selectedLanguageFiles as $slf){
			$checked = false;
			if($slf == $currentLanguageFile){
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
	 * Get selected language files to translate from saved configuration file
	 * @return array selected files
	 */
    protected function getSelectedFilesFromConfiguration(){
		$selectedLanguageFiles = array();
		$selectedLanguageFilesFile = file_get_contents($this->selectedLanguageFilesConfigurationFile);
		if($selectedLanguageFilesFile){
			$selectedLanguageFiles = explode("\n",$selectedLanguageFilesFile);
		}
		return $selectedLanguageFiles;
	}

	/**
	 * Get current assigned file for translation
	 * @return null|string
	 */
	protected function getCurrentLanguageFile(){
		if(is_file($this->currentLanguageFile)){
			$currentLanguageFile = trim(file_get_contents($this->currentLanguageFile));
			$selectedLanguageFiles = $this->getSelectedFilesFromConfiguration();
			//check if file still in selected language files
			if(in_array($currentLanguageFile,$selectedLanguageFiles)){
				return $currentLanguageFile;
			}
		}
		return NULL;
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
			$this->addFlashMessage('','LANGUAGE CACHE CLEARED', 'INFO');
		}
	}

    /**
     * Add flash message - easier severity passing
     *
     * @param $message
     * @param string $header
     * @param string $severity
     */
    public function addFlashMessage($message,$header = '',$severity = 'OK', $storeInSession = TRUE){

        $sev = array(
            'NOTICE', 'INFO', 'OK', 'WARNING', 'ERROR'
        );

        if(in_array($severity,$sev)){

            $message = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                $message,
                $header,
                constant("\\TYPO3\\CMS\\Core\\Messaging\\FlashMessage::".$severity),
                $storeInSession
            );
            $this->controllerContext->getFlashMessageQueue()->addMessage($message);
        }
    }

}
?>