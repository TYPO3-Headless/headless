<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$features = GeneralUtility::makeInstance(Features::class);

$tempColumns = [
    'frontendBase' => [
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendBase.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendBase.description',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local',
        ],
    ],
];

$replaceShowItem = 'base, frontendBase, ';

if ($features->isFeatureEnabled('headless.storageProxy')) {
    $tempColumns['frontendApiProxy'] = [
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendApiProxy.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendApiProxy.description',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local/api',
        ],
    ];

    $tempColumns['frontendFileApi'] = [
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendFileApi.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.frontendFileApi.description',
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
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.cookieDomain.label',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => '.ddev.site',
        ],
    ];

    $replaceShowItem .= 'cookieDomain,';
}

$GLOBALS['SiteConfiguration']['site']['columns']['base']['label'] = 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.base.label';
$GLOBALS['SiteConfiguration']['site']['columns']['base']['description'] = 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:site.columns.base.description';

$GLOBALS['SiteConfiguration']['site']['columns'] = array_merge(
    $GLOBALS['SiteConfiguration']['site']['columns'],
    $tempColumns
);

$GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem'] = str_replace(
    'base,',
    $replaceShowItem,
    $GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem']
);
