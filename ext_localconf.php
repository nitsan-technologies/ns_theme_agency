<?php


// Let's include PageTSconfig
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig("@import 'EXT:ns_theme_agency/Configuration/PageTSconfig/setup.typoscript'");

// Let's add default PageTS for "Form"
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default'] = 'EXT:ns_theme_agency/Configuration/RTE/Default.yaml';