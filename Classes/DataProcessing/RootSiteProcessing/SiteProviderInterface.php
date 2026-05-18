<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing;

use TYPO3\CMS\Core\Site\Entity\Site;

interface SiteProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function prepare(array $config, int $siteUid): self;

    /**
     * @return array<Site>
     */
    public function getSites(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPages(): array;

    /**
     * @return Site
     */
    public function getCurrentRootPage(): Site;
}
