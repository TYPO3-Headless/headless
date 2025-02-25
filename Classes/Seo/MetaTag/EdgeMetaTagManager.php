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
class EdgeMetaTagManager extends AbstractMetaTagManager
{
    /**
     * @var string[][]
     */
    protected $handledProperties = [
        'x-ua-compatible' => ['nameAttribute' => 'http-equiv'],
    ];
}
