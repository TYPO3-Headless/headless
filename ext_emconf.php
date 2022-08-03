<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Headless',
    'description' => 'This extension provides way to output content from TYPO3 in JSON format.',
    'state' => 'stable',
    'author' => 'Łukasz Uznański',
    'author_email' => 'extensions@macopedia.pl',
    'author_company' => 'Macopedia Sp. z o.o.',
    'category' => 'fe',
    'internal' => '',
    'version' => '3.1.1',
    'constraints' => [
        'depends' => [
            'frontend' => '11.4.0-11.5.99',
            'typo3' => '11.4.0-11.5.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
