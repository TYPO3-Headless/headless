<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$features = GeneralUtility::makeInstance(Features::class);

$tempColumns = [
    'frontendBase' => [
        'label' => 'Frontend Entry Point',
        'description' => 'Main URL to call the frontend in default language.',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local',
        ],
    ],
    'headless' => [
        'label' => 'Headless mode',
        'description' => 'How site should behave in headless context',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => HeadlessMode::NONE,
            'items' => [
                ['LLL:EXT:headless/Resources/Private/Language/locallang_be.xlf:site.headless.none', HeadlessMode::NONE],
                ['LLL:EXT:headless/Resources/Private/Language/locallang_be.xlf:site.headless.full', HeadlessMode::FULL],
                ['LLL:EXT:headless/Resources/Private/Language/locallang_be.xlf:site.headless.mixed', HeadlessMode::MIXED],
            ],
        ],
    ]
];

$replaceShowItem = 'base, frontendBase, headless,';

if ($features->isFeatureEnabled('headless.storageProxy')) {
    $tempColumns['frontendApiProxy'] = [
        'label' => 'Frontend API proxy url',
        'description' => 'Main URL to for proxy API',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local/api',
        ],
    ];

    $tempColumns['frontendFileApi'] = [
        'label' => 'Frontend API proxy url for files',
        'description' => 'Main URL to for proxy API files',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local/api/fileadmin',
        ],
    ];

    $replaceShowItem .= 'frontendApiProxy, frontendFileApi,';
}

if ($features->isFeatureEnabled('headless.cookieDomainPerSite')) {
    $tempColumns['cookieDomain'] = [
        'label' => 'Cookie Domain',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => '.ddev.site',
        ],
    ];

    $replaceShowItem .= 'cookieDomain,';
}

$GLOBALS['SiteConfiguration']['site']['columns']['base']['label'] = 'TYPO3 Entry Point';
$GLOBALS['SiteConfiguration']['site']['columns']['base']['description'] = 'Main URL to call the TYPO3 headless api in default language.';

$GLOBALS['SiteConfiguration']['site']['columns'] = array_merge(
    $GLOBALS['SiteConfiguration']['site']['columns'],
    $tempColumns
);

$GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem'] = str_replace(
    'base,',
    $replaceShowItem,
    $GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem']
);
