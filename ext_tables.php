<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

defined('TYPO3_MODE') || die();

call_user_func(
    static function () {
        $features = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class);
        $typo3Version = new TYPO3\CMS\Core\Information\Typo3Version();

        if ($features->isFeatureEnabled('headless.frontendUrls')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'][] = \FriendsOfTYPO3\Headless\Hooks\PreviewUrlHook::class;
        }

        if ($features->isFeatureEnabled('headless.jsonViewModule')) {
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Headless',
                'web',
                'jsonview',
                'bottom',
                [
                    \FriendsOfTYPO3\Headless\Controller\JsonViewController::class => 'main'
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
