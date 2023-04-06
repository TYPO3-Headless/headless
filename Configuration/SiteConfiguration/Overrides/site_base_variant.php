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
        'label' => 'Frontend Entry Point',
        'description' => 'For example "https://front.staging.domain.tld" or "http://front.domain.local"',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://www.domain.local',
        ],
    ],
];

$replaceShowItem = 'base, frontendBase,';

if ($features->isFeatureEnabled('headless.storageProxy')) {
    $tempColumns['frontendApiProxy'] = [
        'label' => 'Frontend API proxy url',
        'description' => 'Main URL to for proxy API',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://front-domain.tld/api',
        ],
    ];

    $tempColumns['frontendFileApi'] = [
        'label' => 'Frontend API proxy url for files',
        'description' => 'Main URL to for proxy API files',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
            'placeholder' => 'http://front-domain.tld/api/fileadmin',
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

$GLOBALS['SiteConfiguration']['site_base_variant']['columns'] = array_merge(
    $GLOBALS['SiteConfiguration']['site_base_variant']['columns'],
    $tempColumns
);
$GLOBALS['SiteConfiguration']['site_base_variant']['columns']['base']['label'] = 'TYPO3 Entry Point';

$GLOBALS['SiteConfiguration']['site_base_variant']['types']['1']['showitem'] = str_replace(
    'base,',
    $replaceShowItem,
    $GLOBALS['SiteConfiguration']['site_base_variant']['types']['1']['showitem']
);
