<?php
// TYPO3 Security Check
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$_EXTKEY = "ns_theme_agency";

// Add default include static TypoScript (for root page)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('ns_theme_agency', 'Configuration/TypoScript', '[NITSAN] Child Theme & Templates');