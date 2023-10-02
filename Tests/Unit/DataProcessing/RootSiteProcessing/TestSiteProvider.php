<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class TestSiteProvider implements SiteProviderInterface
{
    public function prepare(array $config, int $siteUid) {}

    public function getSites(): array
    {
        return [new Site('test_site', 1, [ 'base' => 'https://www.typo3.org']), new Site('test_site2', 2, [ 'base' => 'https://forge.typo3.org'])];
    }

    public function getPages(): array
    {
        return [];
    }

    public function getCurrentRootPage(): Site
    {
        return new Site('test_site', 1, []);
    }
}
