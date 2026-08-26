<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(static function () {
    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript/Headless',
        'Headless'
    );

    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript',
        'Headless Legacy (4.x)'
    );

    ExtensionManagementUtility::addStaticFile(
        'headless',
        'Configuration/TypoScript/Mixed',
        'Headless - Mixed mode JSON response'
    );
});
