<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FriendsOfTYPO3\Headless\Middleware\ElementBodyResponseMiddleware;
use FriendsOfTYPO3\Headless\Middleware\RedirectHandler;
use FriendsOfTYPO3\Headless\Middleware\ShortcutAndMountPointRedirect;
use FriendsOfTYPO3\Headless\Middleware\UserIntMiddleware;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;

return (static function (): array {
    $features = GeneralUtility::makeInstance(Features::class);

    $middlewares = [
        'frontend' => [
            'headless/cms-frontend/prepare-user-int' => [
                'after' => [
                    'typo3/cms-frontend/content-length-headers',
                ],
                'target' => UserIntMiddleware::class,
            ],
        ],
    ];

    if ($features->isFeatureEnabled('headless.elementBodyResponse')) {
        $middlewares['frontend']['headless/cms-frontend/element-body-response'] = [
            'after' => [
                'typo3/cms-adminpanel/data-persister',
            ],
            'target' => ElementBodyResponseMiddleware::class,
        ];
    }

    if ($features->isFeatureEnabled('headless.cookieDomainPerSite')) {
        $middlewares['backend'] = [
            'headless/cms-backend/cookie-domain-middleware' => [
                'before' => [
                    'typo3/cms-backend/authentication',
                ],
                'target' => \FriendsOfTYPO3\Headless\Middleware\CookieDomainPerSite::class,
            ],
        ];
    }

    if (!$features->isFeatureEnabled('headless.redirectMiddlewares')) {
        return $middlewares;
    }

    return array_merge_recursive($middlewares, [
        'frontend' => [
            'typo3/cms-redirects/redirecthandler' => [
                'disabled' => true,
            ],
            'typo3/cms-frontend/shortcut-and-mountpoint-redirect' => [
                'disabled' => true,
            ],
            'headless/cms-redirects/redirecthandler' => [
                'target' => RedirectHandler::class,
                'before' => [
                    'typo3/cms-frontend/base-redirect-resolver',
                ],
                'after' => [
                    'typo3/cms-frontend/authentication',
                ],
            ],
            'headless/cms-frontend/shortcut-and-mountpoint-redirect' => [
                'target' => ShortcutAndMountPointRedirect::class,
                'after' => [
                    'typo3/cms-frontend/prepare-tsfe-rendering',
                ],
                'before' => [
                    'typo3/cms-frontend/content-length-headers',
                ],
            ],
        ],
    ]);
})();
