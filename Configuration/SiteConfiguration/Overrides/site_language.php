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
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendBase.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendBase.description',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local/en/',
        ],
    ],
];

$appendShowItem = ', frontendBase';

if ($features->isFeatureEnabled('headless.storageProxy')) {
    $tempColumns['frontendApiProxy'] = [
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendApiProxy.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendApiProxy.description',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://front-domain.tld/api',
        ],
    ];

    $tempColumns['frontendFileApi'] = [
        'label' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendFileApi.label',
        'description' => 'LLL:EXT:headless/Resources/Private/Language/locallang_siteconfiguration.xlf:siteBaseVariant.columns.frontendFileApi.description',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://front-domain.tld/api/fileadmin',
        ],
    ];

    $appendShowItem .= ', frontendApiProxy, frontendFileApi';
}

$GLOBALS['SiteConfiguration']['site_language']['columns'] = array_merge(
    $GLOBALS['SiteConfiguration']['site_language']['columns'],
    $tempColumns
);

$GLOBALS['SiteConfiguration']['site_language']['palettes']['default']['showitem'] .= $appendShowItem;
