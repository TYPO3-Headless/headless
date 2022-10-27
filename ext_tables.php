<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FriendsOfTYPO3\Headless\Controller\JsonViewController;
use FriendsOfTYPO3\Headless\Hooks\PreviewUrlHook;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

call_user_func(
    static function () {
        $features = GeneralUtility::makeInstance(Features::class);

        if ($features->isFeatureEnabled('headless.frontendUrls')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'][] = PreviewUrlHook::class;
        }

        if ($features->isFeatureEnabled('headless.jsonViewModule')) {
            ExtensionUtility::registerModule(
                'Headless',
                'web',
                'jsonview',
                'bottom',
                [
                    JsonViewController::class => 'main'
                ],
                [
                    'access' => 'admin',
                    'icon' => 'EXT:headless/Resources/Public/Icons/module-jsonview.svg',
                    'labels' => 'LLL:EXT:headless/Resources/Private/Language/locallang_mod.xlf'
                ]
            );
        }
    }
);
