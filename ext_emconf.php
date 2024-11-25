<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 Headless API',
    'description' => 'Makes TYPO3 a headless CMS. Content and pages available in JSON format. Supports multilanguage, multidomain, forms, frontend login, workspaces and more. For JS frontend app see nuxt-typo3 package',
    'state' => 'stable',
    'author' => 'Łukasz Uznański',
    'author_email' => 'extensions@macopedia.pl',
    'author_company' => 'Macopedia Sp. z o.o.',
    'category' => 'fe',
    'version' => '3.4.3',
    'constraints' => [
        'depends' => [
            'frontend' => '11.4.0-11.5.99',
            'typo3' => '11.5.29-11.5.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
