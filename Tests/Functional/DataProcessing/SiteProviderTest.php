<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use InvalidArgumentException;
use stdClass;
use TYPO3\CMS\Core\Core\Environment;

use TYPO3\CMS\Core\Site\Entity\Site;

use function array_map;

/**
 * SiteProvider runs real database queries (Doctrine Result is final and
 * cannot be doubled), so its coverage lives here.
 */
class SiteProviderTest extends BaseHeadlessTesting
{
    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/root_sites.csv');

        $this->writeHeadlessSite('site_a', 110, 'https://site-a.local/');
        $this->writeHeadlessSite('site_b', 120, 'https://site-b.local/');
        $this->writeHeadlessSite('site_c', 130, 'https://site-c.local/');
    }

    public function testSitesAreFilteredToHeadlessOnesAndSortedBySortingField(): void
    {
        $provider = $this->get(SiteProvider::class)->prepare([], 110);

        // The base "headless" site (root page 1) has no `headless` flag and is filtered out;
        // the remaining sites are ordered by pages.sorting (B=10, A=20, C=30).
        self::assertSame([120, 110, 130], $this->rootPageIds($provider->getSites()));
        self::assertSame(110, $provider->getCurrentRootPage()->getRootPageId());

        $pages = $provider->getPages();
        self::assertSame('Site A', $pages[110]['title']);
        self::assertSame(10, (int)$pages[120]['sorting']);
    }

    public function testAllowedSitesRestrictTheResult(): void
    {
        $provider = $this->get(SiteProvider::class)->prepare(['allowedSites' => '110,130'], 110);

        self::assertSame([110, 130], $this->rootPageIds($provider->getSites()));
    }

    public function testSitesFromPidFetchesRootSitesByParentPage(): void
    {
        $provider = $this->get(SiteProvider::class)->prepare(['sitesFromPid' => 999], 110);

        self::assertSame([130], $this->rootPageIds($provider->getSites()));
    }

    public function testInvalidSortingImplementationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->get(SiteProvider::class)->prepare(['sortingImplementation' => stdClass::class], 110);
    }

    /**
     * @param array<Site> $sites
     * @return list<int>
     */
    private function rootPageIds(array $sites): array
    {
        return array_map(static fn(Site $site): int => $site->getRootPageId(), array_values($sites));
    }

    private function writeHeadlessSite(string $identifier, int $rootPageId, string $base): void
    {
        $siteConfigDir = Environment::getConfigPath() . '/sites/' . $identifier;

        mkdir($siteConfigDir, 0777, true);

        file_put_contents(
            $siteConfigDir . '/config.yaml',
            "rootPageId: $rootPageId\nbase: $base\nheadless: 1\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );
    }
}
