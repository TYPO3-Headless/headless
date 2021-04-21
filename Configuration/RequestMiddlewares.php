<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

return (static function (): array {
    $features = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class);

    if (!$features->isFeatureEnabled('headless.redirectMiddlewares')) {
        return [];
    }

    return [
        'frontend' => [
            'typo3/cms-frontend/shortcut-and-mountpoint-redirect' => [
                'disabled' => true
            ],
            'headless/cms-frontend/shortcut-and-mountpoint-redirect' => [
                'target' => \FriendsOfTYPO3\Headless\Middleware\ShortcutAndMountPointRedirect::class,
                'after' => [
                    'typo3/cms-frontend/prepare-tsfe-rendering',
                ],
                'before' => [
                    'typo3/cms-frontend/content-length-headers',
                ],
            ],
        ]
    ];
})();
