<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo\MetaTag;

/**
 * Overridden core version with headless implementation
 */
class TwitterCardMetaTagManager extends AbstractMetaTagManager
{
    /**
     * Array of properties that can be handled by this manager
     *
     * @var array
     */
    protected $handledProperties = [
        'twitter:card' => [],
        'twitter:site' => [
            'allowedSubProperties' => [
                'id' => [],
            ],
        ],
        'twitter:creator' => [
            'allowedSubProperties' => [
                'id' => [],
            ],
        ],
        'twitter:description' => [],
        'twitter:title' => [],
        'twitter:image' => [
            'allowedSubProperties' => [
                'alt' => [],
            ],
        ],
        'twitter:player' => [
            'allowedSubProperties' => [
                'width' => [],
                'height' => [],
                'stream' => [],
            ],
        ],
        'twitter:app' => [
            'allowedSubProperties' => [
                'name:iphone' => [],
                'id:iphone' => [],
                'url:iphone' => [],
                'name:ipad' => [],
                'id:ipad' => [],
                'url:ipad' => [],
                'name:googleplay' => [],
                'id:googleplay' => [],
                'url:googleplay' => [],
            ],
        ],
    ];
}
