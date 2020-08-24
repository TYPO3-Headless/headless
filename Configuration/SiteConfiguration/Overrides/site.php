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
            'description' => 'Main URL to call the frontend in default language.',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'placeholder' => 'http://www.domain.local',
            ],
        ]
    ];

    $GLOBALS['SiteConfiguration']['site']['columns']['base']['label'] = 'TYPO3 Entry Point';
    $GLOBALS['SiteConfiguration']['site']['columns']['base']['description'] = 'Main URL to call the TYPO3 headless api in default language.';

    $GLOBALS['SiteConfiguration']['site']['columns'] = array_merge($GLOBALS['SiteConfiguration']['site']['columns'], $tempColumns);

    $GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem'] = str_replace(
        'base,',
        'base, frontendBase, ',
        $GLOBALS['SiteConfiguration']['site']['palettes']['base']['showitem']
    );
}
