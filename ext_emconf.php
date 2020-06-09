<?php
/*
 * This file is part of the package nitsan/ns-basetheme.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

// Provide detailed information and depenencies of EXT:ns_theme_agency
$EM_CONF['ns_theme_agency'] = array(
	'title' => '[NITSAN] Agency TYPO3 Template',
	'description' => 'Agency TYPO3 template is an ultimate tool to kickstart your project, either a software development company, or a product startup or any new business. Live-Demo: https://demo.t3terminal.com/?theme=t3t-agency PRO version: https://t3terminal.com/t3-agency-free-business-typo3-template/',
	'category' => 'templates',
	'author' => 'T3:Sonal Chauhan, FE: Nisha Ghodadra, QA:Siddharth Sheth',
	'author_email' => 'info@nitsan.in',
	'author_company' => 'NITSAN Technologies Pvt Ltd',
	'state' => 'stable',
	'version' => '2.0.0',
	'constraints' => array(
		'depends' => array(
            'typo3' => '8.0.0-10.9.99',
			'ns_basetheme' => '1.0.0-10.9.99',
			'gridelements' => '8.0.0-10.9.99',
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
?>
