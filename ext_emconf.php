<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'headless',
    'description' => 'This extension provides way to output content from TYPO3 in JSON format.',
    'state' => 'beta',
    'author' => 'Łukasz Uznański',
    'author_email' => 'extensions@macopedia.pl',
    'category' => 'fe',
    'internal' => '',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.0.99',
            'frontend' => '9.5.0-10.0.99'
        ],
        'suggests' => [],
    ],
];
