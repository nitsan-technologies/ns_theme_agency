<?php
// TYPO3 Security Check
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// Let's configuration of this extension from "Extension Manager"
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY] = unserialize($_EXTCONF);

// Let's include PageTSconfig
if (TYPO3_MODE === 'BE') {
    call_user_func(
        function ($_EXTKEY) {
            // Let's add default PageTSConfig for Backend layout, TCE form, Components etc.,
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTSconfig/setup.ts">');
        },
        $_EXTKEY
    );
}