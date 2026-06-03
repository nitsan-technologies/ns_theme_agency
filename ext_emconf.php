<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ns_theme_agency".
 *
 * Auto generated 11-05-2023 13:16
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['ns_theme_agency'] = array (
  'title' => 'T3 Agency – Modern TYPO3 Template',
  'description' => 'A professional TYPO3 website template ideal for software companies, startups, and digital agencies. Fully responsive and compatible with TYPO3 v14.',
  'category' => 'templates',
  'version' => '14.0.0',
  'state' => 'stable',
  'uploadfolder' => false,
  'author' => 'Team T3Planet',
  'author_email' => 'info@t3planet.de',
  'author_company' => 'T3Planet',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '14.0.0-14.9.99',
      'ns_basetheme' => '14.0.0-14.9.99',
      'news' => '14.0.0-14.9.99',
      'content_blocks' => '14.0.0-14.9.99',
    ),
    'conflicts' => 
    array (
      'ns_theme_bootstrap' => 'T3 Bootstrap and T3 Agency define the same content block names (e.g. nitsan/ns-banner). Use only one theme extension.',
    ),
    'suggests' => 
    array (
    ),
  ),
);

