<?php

$header = <<<EOF
This file is part of the "headless" Extension for TYPO3 CMS.

For the full copyright and license information, please read the
LICENSE.md file that was distributed with this source code.
EOF;

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()->in('Classes')->in('Configuration')->in('Tests');
$rules = $config->getRules();
$rules['ordered_imports'] = [
    'imports_order' => [
        'class',
        'function',
        'const',
    ],
    'sort_algorithm' => 'alpha',
];
$rules['header_comment'] = [
    'header' => $header,
    'comment_type' => 'comment',
    'location' => 'after_open',
    'separate' => 'both'
];

$config->setRules($rules);
return $config;