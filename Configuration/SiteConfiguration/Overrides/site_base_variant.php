<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

if (\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class)->isFeatureEnabled('FrontendBaseUrlInPagePreview')) {
    $tempColumns = [
        'frontendBase' => [
            'label' => 'Frontend Entry Point',
            'description' => 'For example "https://front.staging.domain.tld" or "http://front.domain.local"',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'placeholder' => 'http://www.domain.local',
            ],
        ]
    ];

    $GLOBALS['SiteConfiguration']['site_base_variant']['columns'] = array_merge($GLOBALS['SiteConfiguration']['site_base_variant']['columns'], $tempColumns);
    $GLOBALS['SiteConfiguration']['site_base_variant']['columns']['base']['label'] = 'TYPO3 Entry Point';

    $GLOBALS['SiteConfiguration']['site_base_variant']['types']['1']['showitem'] = str_replace(
        'base,',
        'base, frontendBase, ',
        $GLOBALS['SiteConfiguration']['site_base_variant']['types']['1']['showitem']
    );
}
