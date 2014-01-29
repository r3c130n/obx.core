<?php
/***********************************************
 ** @product OBX:Core Bitrix Module           **
 ** @authors                                  **
 **         Maksim S. Makarov aka pr0n1x      **
 ** @license Affero GPLv3                     **
 ** @mailto rootfavell@gmail.com              **
 ** @copyright 2013 DevTop                    **
 ***********************************************/

namespace OBX\Core;

use OBX\Core\Exceptions\LessCSSError;

class LessCSS {
	static protected $_arInstances = array();
	public function getInstance($templateName) {
		if( array_key_exists($templateName, static::$_arInstances) ) {
			return static::$_arInstances[$templateName];
		}
		$class = get_called_class();
		/**
		 * @var self $LessConnector
		 */
		$LessConnector = new $class($templateName);
		static::$_arInstances[$templateName] = $LessConnector;
		return $LessConnector;
	}

	protected $_lessTemplateID = null;
	protected $_arLessFiles = array();
	protected $_arLessFilesSort = array();
	protected $_lessFilesCounter = 0;
	protected $_bLessProduction = null;
	protected $_lessCompiledExt = '.css';
	protected $_lessJSPath = null;
	protected $_bLessFilesConnected = false;
	protected $_bLessJSHeadConnected = false;
	protected $_bConnectLessJSFileAfterLessFiles = false;

	public function __construct($templateID) {
		Tools::_fixFileName($templateID);
		if( !is_dir(OBX_DOC_ROOT.BX_ROOT.'/templates/'.$templateID)
			|| !is_file(OBX_DOC_ROOT.BX_ROOT.'/templates/'.$templateID.'header.php')
		) {
			throw new LessCSSError('', LessCSSError::E_TEMPLATE_NOT_FOUND);
		}
	}

	public function isLessProductionReady() {
		if($this->_bLessProduction === null) {
			$optLessProduction = \COption::GetOptionString('obx.core', 'LESSCSS_PROD_READY_'.$this->_lessTemplateID, 'N');
			if($optLessProduction == 'Y') {
				$this->_bLessProduction = true;
			}
			else {
				$this->_bLessProduction = false;
			}
		}
		return $this->_bLessProduction;
	}

	public function getLessHead() {
		$returnString = '';
		uksort($this->_arLessFiles, '\OBX\Core\Tools::__sortLessFiles');
		foreach($this->_arLessFiles as $lessFilePath) {
			$compiledLessFilePath = substr($lessFilePath, 0, -5).$this->_lessCompiledExt;
			if(!self::isLessProductionReady()) {
				$returnString .= '<link rel="stylesheet/less" type="text/css" href="'.$lessFilePath.'">'."\n";
			}
			else {
				$returnString .= '<link rel="stylesheet" type="text/css" href="'.$compiledLessFilePath.'">'."\n";
			}
		}
		return $returnString;
	}
	public function getLessJSHead() {
		$returnString = '';
		if( $this->_lessJSPath ) {
			$returnString .= '<script type="text/javascript"> less = { env: \'development\' }; </script>'."\n";
			$returnString .= '<script type="text/javascript" src="'.$this->_lessJSPath.'"></script>'."\n";
			//$returnString .= '<script type="text/javascript">less.watch();</script>'."\n";
		}
		return $returnString;
	}
	public function showLessHead() {
		global $APPLICATION;
		$APPLICATION->AddBufferContent('OBX\Core\Tools::getLessHead');
		$this->_bLessFilesConnected = true;
		if( $this->_bConnectLessJSFileAfterLessFiles ) {
			$APPLICATION->AddBufferContent('OBX\Core\Tools::getLessJSHead');
			$this->_bConnectLessJSFileAfterLessFiles = false;
			$this->_bLessJSHeadConnected = true;
		}
	}
	public function showLessJSHead($bWaitWhileLessFilesConnected = true) {
		if( $bWaitWhileLessFilesConnected && !$this->_bLessFilesConnected ) {
			$this->_bConnectLessJSFileAfterLessFiles = true;
			return;
		}
		global $APPLICATION;
		$APPLICATION->AddBufferContent('OBX\Core\Tools::getLessJSHead');
		$this->_bLessJSHeadConnected = true;
	}
	public function setLessJSPath($lessJSPath, $bShowLessHead = true) {
		if( strpos($lessJSPath, 'less')===false || substr($lessJSPath, -3)!='.js' ) {
			throw new LessCSSError('', LessCSSError::E_LESS_JS_FILE_NOT_FOUND);
		}
		if( is_file(OBX_DOC_ROOT.$lessJSPath) ) {
			$this->_lessJSPath = $lessJSPath;
		}
		elseif( is_file(OBX_DOC_ROOT.SITE_TEMPLATE_PATH.'/'.$lessJSPath) ) {
			$lessJSPath = str_replace(array('//', '\\'), '/', SITE_TEMPLATE_PATH.'/'.$lessJSPath);
			$lessJSPath = str_replace('../', '', $lessJSPath);
			$lessJSPath = '/'.trim($lessJSPath, '/');
			$this->_lessJSPath = $lessJSPath;
		}
		else {
			throw new LessCSSError('', LessCSSError::E_LESS_JS_FILE_NOT_FOUND);
		}
		if( $bShowLessHead ) {
			if( !$this->_bLessFilesConnected ) {
				$this->_bConnectLessJSFileAfterLessFiles = false;
				self::showLessHead();
			}
			if( !$this->_bLessJSHeadConnected ) {
				self::showLessJSHead();
			}
		}
		return true;
	}
	public function getLessJSPath() {
		return $this->_lessJSPath;
	}
	public function setLessCompiledExt($ext) {
		if( preg_match('~^\.[a-zA-Z0-9\_\-]*\.css$~', $ext)) {
			$this->_lessCompiledExt = $ext;
		}
	}
	/**
	 * @param $lessFilePath
	 * @param int $sort
	 * @return bool
	 */
	public function addLess($lessFilePath, $sort = 500) {
		if( !in_array($lessFilePath, $this->_arLessFiles) ) {
			if( substr($lessFilePath, -5) == '.less' ) {
				$compiledLessFilePath = substr($lessFilePath, 0, -5).$this->_lessCompiledExt;
				$sort = intval($sort);
				if( is_file(OBX_DOC_ROOT.$lessFilePath)
					|| (
						is_file(OBX_DOC_ROOT.$compiledLessFilePath)
						&& self::isLessProductionReady())
				) {
					$this->_arLessFiles[$this->_lessFilesCounter] = $lessFilePath;
					$this->_arLessFilesSort[$this->_lessFilesCounter] = $sort;
					$this->_lessFilesCounter++;
					return true;
				}
				elseif(
					is_file(OBX_DOC_ROOT.SITE_TEMPLATE_PATH.'/'.$lessFilePath)
					|| (
						is_file(OBX_DOC_ROOT.SITE_TEMPLATE_PATH.'/'.$compiledLessFilePath)
						&& $this->isLessProductionReady()
					)
					//					// На случай если мы будем комипировать less в папку css
					//					|| (
					//						substr($compiledLessFilePath, 0, 5) == 'less/'
					//						&& $this->isLessProductionReady()
					//						&& is_file(OBX_DOC_ROOT.SITE_TEMPLATE_PATH.'/css/'.substr($compiledLessFilePath, 5))
					//					)
				) {
					$this->_arLessFiles[$this->_lessFilesCounter] = SITE_TEMPLATE_PATH.'/'.$lessFilePath;
					$this->_arLessFilesSort[$this->_lessFilesCounter] = $sort;
					$this->_lessFilesCounter++;
					return true;
				}
			}
		}
		return false;
	}
	public function getLessFilesList($lessCompiledFileExt = null) {
		if($lessCompiledFileExt === null) {
			$this->setLessCompiledExt($lessCompiledFileExt);
		}
		return $this->_arLessFiles;
	}
	/**
	 * @static
	 * @param \CBitrixComponent|\CBitrixComponentTemplate|string $component
	 * @param null $lessFilePath
	 * @param $sort
	 * @return bool
	 */
	public function addComponentLess($component, $lessFilePath = null, $sort = 500) {
		/**
		 * @global \CMain $APPLICATION
		 */
		$templateFolder = null;
		if($component instanceof \CBitrixComponent) {
			$templateFolder = $component->__template->__folder;
		}
		elseif($component instanceof \CBitrixComponentTemplate) {
			$template = &$component;
			$templateFolder = $template->__folder;
		}
		elseif( is_string($component) ) {
			$component = str_replace(array('\\', '//'), '/', $component);
			if(
				($bxrootpos = strpos($component, BX_ROOT.'/templates')) !== false
				||
				($bxrootpos = strpos($component, BX_ROOT.'/components')) !== false
			) {
				$component = substr($component, $bxrootpos);
			}
			if( ($extpos = strrpos($component, '.php')) !== false
				|| ($extpos = strrpos($component, '.less')) !== false
			) {
				if( $dirseppos = strrpos($component, '/') ) {
					$templateFolder = substr($component, 0, $dirseppos);
					if($lessFilePath == null && strrpos($component, '.less') !== false) {
						$lessFilePath = substr($component, $dirseppos);
						$lessFilePath = ltrim($lessFilePath, '/');
					}
				}
			}
			else {
				$templateFolder = $component;
			}
		}
		$sort = intval($sort);
		if( $lessFilePath == null ) {
			if( is_file(OBX_DOC_ROOT.$templateFolder.'/style.less')
				|| (is_file(OBX_DOC_ROOT.$templateFolder.'/style.less.css')
					&& $this->isLessProductionReady())
			) {
				$lessFilePath = str_replace(
					array('//', '///'), array('/', '/'),
					$templateFolder.'/style.less'
				);
				if( !in_array($lessFilePath, $this->_arLessFiles) ) {
					$this->_arLessFiles[$this->_lessFilesCounter] = $lessFilePath;
					$this->_arLessFilesSort[$this->_lessFilesCounter] = $sort;
					$this->_lessFilesCounter++;
					return true;
				}
				return true;
			}
		}
		elseif( is_file(OBX_DOC_ROOT.$templateFolder.'/'.$lessFilePath)
			|| (is_file(OBX_DOC_ROOT.$templateFolder.'/'.$lessFilePath.'.css')
				&& $this->isLessProductionReady() )
		) {
			$lessFilePath = str_replace(
				array('//', '///'), array('/', '/'),
				$templateFolder.'/'.$lessFilePath
			);
			if( substr($lessFilePath, -5) == '.less' ) {
				if( !in_array($lessFilePath, $this->_arLessFiles) ) {
					$this->_arLessFiles[$this->_lessFilesCounter] = $lessFilePath;
					$this->_arLessFilesSort[$this->_lessFilesCounter] = $sort;
					$this->_lessFilesCounter++;
					return true;
				}
			}
		}
		return false;
	}
	public function setLessProductionReady($bCompiled = true) {
		$this->_bLessProduction = ($bCompiled)?true:false;
	}
	
	public function __sortLessFiles($fileIndexA, $fileIndexB) {
		$sortA = intval($this->_arLessFilesSort[$fileIndexA] * 100 + $fileIndexA);
		$sortB = intval($this->_arLessFilesSort[$fileIndexB] * 100 + $fileIndexB);
		if($sortA == $sortB) return 0;
		return ($sortA < $sortB)? -1 : 1;
	}

	static public function showLessBufferContentHead($templateID){
		
	}
}