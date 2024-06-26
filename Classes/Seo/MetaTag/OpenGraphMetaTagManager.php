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
class OpenGraphMetaTagManager extends AbstractMetaTagManager
{
    /**
     * The default attribute that defines the name of the property
     *
     * This creates tags like <meta property="" /> by default
     *
     * @var string
     */
    protected $defaultNameAttribute = 'property';

    /**
     * Array of properties that can be handled by this manager
     *
     * @var array
     */
    protected $handledProperties = [
        'og:type' => [],
        'og:title' => [],
        'og:description' => [],
        'og:site_name' => [],
        'og:url' => [],
        'og:audio' => [],
        'og:video' => [],
        'og:determiner' => [],
        'og:locale' => [
            'allowedSubProperties' => [
                'alternate' => [
                    'allowMultipleOccurrences' => true,
                ],
            ],
        ],
        'og:image' => [
            'allowMultipleOccurrences' => true,
            'allowedSubProperties' => [
                'url' => [],
                'secure_url' => [],
                'type' => [],
                'width' => [],
                'height' => [],
                'alt' => [],
            ],
        ],
    ];
}
