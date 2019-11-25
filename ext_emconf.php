<?php
// TYPO3 Security Check
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// Provide detailed information and depenencies of EXT:ns_theme_agency
$EM_CONF[$_EXTKEY] = array(
	'title' => '[NITSAN] Agency TYPO3 Theme',
	'description' => 'Agency theme is an ultimate tool to kickstart your project, either a software development company, or a product startup or any new business. Demo: https://demo.t3terminal.com/t3t-agency/ PRO version: https://t3terminal.com/t3-agency-free/',
	'category' => 'templates',
	'author' => 'T3:Ravi Nagaiya, FE: Nisha Ghodadra, QA:Siddharth Sheth',
	'author_email' => 'info@nitsan.in',
	'author_company' => 'NITSAN Technologies Pvt Ltd',
	'state' => 'stable',
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '8.0.0-9.9.99',
            'ns_basetheme' => '1.0.0-9.9.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	//'autoload' => array(
	//	'classmap' => array('Classes/'),
	//),
);
