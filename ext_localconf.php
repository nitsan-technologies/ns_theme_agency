<?php

defined('TYPO3') or die();

// Page TSconfig: via site set (Configuration/Sets/ns_theme_agency/config.yaml → pagets).
// addPageTSConfig() was removed in TYPO3 v14.

$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default'] = 'EXT:ns_theme_agency/Configuration/RTE/Default.yaml';
