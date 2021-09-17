<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Headless',
    'description' => 'This extension provides way to output content from TYPO3 in JSON format.',
    'state' => 'stable',
    'author' => 'Łukasz Uznański',
    'author_email' => 'extensions@macopedia.pl',
    'category' => 'fe',
    'internal' => '',
    'version' => '2.6.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.4.0-11.5.99',
            'frontend' => '11.4.0-11.5.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
