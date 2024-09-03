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
class Html5MetaTagManager extends AbstractMetaTagManager
{
    /**
     * Array of properties that can be handled by this manager
     *
     * @var array
     */
    protected $handledProperties = [
        'application-name' => [],
        'author' => [],
        'description' => [],
        'generator' => [],
        'keywords' => [],
        'referrer' => [],
        'content-language' => [
            'nameAttribute' => 'http-equiv',
        ],
        'content-type' => [
            'nameAttribute' => 'http-equiv',
        ],
        'default-style' => [
            'nameAttribute' => 'http-equiv',
        ],
        'refresh' => [
            'nameAttribute' => 'http-equiv',
        ],
        'set-cookie' => [
            'nameAttribute' => 'http-equiv',
        ],
        'content-security-policy' => [
            'nameAttribute' => 'http-equiv',
        ],
        'viewport' => [],
        'robots' => [],
        'expires' => [
            'nameAttribute' => 'http-equiv',
        ],
        'cache-control' => [
            'nameAttribute' => 'http-equiv',
        ],
        'pragma' => [
            'nameAttribute' => 'http-equiv',
        ],
    ];
}
