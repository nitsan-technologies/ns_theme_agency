<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Page TSconfig: via site set (Configuration/Sets/ns_theme_agency/config.yaml → pagets).
// addPageTSConfig() was removed in TYPO3 v14.

$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default'] = 'EXT:ns_theme_agency/Configuration/RTE/Default.yaml';

// Optional dependency: templates use <f:mark.contentArea> for visual editor support.
// When visual_editor is not installed, provide a passthrough fallback on the f: namespace.
if (!ExtensionManagementUtility::isLoaded('visual_editor')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['f'][] = 'NITSAN\\NsThemeAgency\\ViewHelpers';
}

