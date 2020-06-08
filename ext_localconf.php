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
        function ('ns_theme_agency') {
            // Let's add default PageTSConfig for Backend layout, TCE form, Components etc.,
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:ns_theme_agency/Configuration/PageTSconfig/setup.ts">');
        },
        $_EXTKEY
    );
}