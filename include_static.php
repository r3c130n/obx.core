<?php
/***********************************************
 ** @product OBX:Core Bitrix Module           **
 ** @authors                                  **
 **         Maksim S. Makarov aka pr0n1x      **
 ** @license Affero GPLv3                     **
 ** @mailto rootfavell@gmail.com              **
 ** @copyright 2013 DevTop                    **
 ***********************************************/

require __DIR__.'/classes/.constants.php';
$arModuleClasses = require __DIR__.'/classes/.classes.php';
foreach ($arModuleClasses as $class => $classPath) {
	if(in_array($class, $arStaticIncludeSkip)) {
		continue;
	}
	$classPath = __DIR__.'/'.$classPath;
	if(is_file($classPath)) {
		require_once $classPath;
	}
}
?>
