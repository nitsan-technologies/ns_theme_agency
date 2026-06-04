<?php
// TYPO3 Security
defined('TYPO3') or die();


call_user_func(function () {
});
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content', // table
    'CType', // typeField
    't3themeagency_content_blocks', // group
    'LLL:EXT:ns_theme_agency/Resources/Private/Language/locallang.xlf:contentBlocks.blocks', // label
    'before:default', // position
);