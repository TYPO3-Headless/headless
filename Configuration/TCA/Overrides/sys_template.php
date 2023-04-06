<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(static function () {
    /**
     * Default TypoScript for Headless
     */
    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript',
        'Headless'
    );
    /**
     * Mixed-Mode TypoScript for Headless
     */
    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript/Mixed',
        'Headless - Mixed mode JSON response'
    );
    /**
     * 2.x JSON response
     */
    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript/2.x',
        'Headless - 2.x JSON response'
    );
});
