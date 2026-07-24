<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Page TSconfig: via site set (Configuration/Sets/ns_theme_agency/config.yaml → pagets).
// addPageTSConfig() was removed in TYPO3 v14.

$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default'] = 'EXT:ns_theme_agency/Configuration/RTE/Default.yaml';

// TYPO3 v14 visual_editor fallback (unused on v13 page templates).
if (!ExtensionManagementUtility::isLoaded('visual_editor')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['f'][] = 'NITSAN\\NsThemeAgency\\ViewHelpers';
}

