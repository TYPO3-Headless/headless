<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing;

interface SiteSchemaInterface
{
    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function process(SiteProviderInterface $provider, array $options = []): array;
}
