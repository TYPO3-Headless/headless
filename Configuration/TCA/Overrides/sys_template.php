<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

defined('TYPO3_MODE') || die();

call_user_func(static function () {
    /**
     * Default TypoScript for Headless
     */
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript',
        'Headless'
    );
    /**
     * 2.x JSON response
     */
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript/2.x',
        'Headless - 2.x JSON response'
    );
});
