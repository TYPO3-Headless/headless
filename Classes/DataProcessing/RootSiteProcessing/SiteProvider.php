<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function usort;

class SiteProvider implements SiteProviderInterface
{
    /**
     * @var ConnectionPool
     */
    private $connectionPool;
    /**
     * @var SiteFinder
     */
    private $siteFinder;
    /**
     * @var Site[]
     */
    private $sites;
    /**
     * @var array[]
     */
    private $pagesData;
    /**
     * @var Site
     */
    private $currentRootPage;

    public function __construct(ConnectionPool $connectionPool = null, SiteFinder $siteFinder = null)
    {
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * @param array<string, mixed> $config
     * @param int $siteUid
     */
    public function prepare(array $config, int $siteUid): self
    {
        $allowedSites = $config['allowedSites'] ?? null;
        $sortingField = $config['sortingField'] ?? 'sorting';
        $customSorting = $config['sortingImplementation'] ?? null;

        if ($sortingField === '') {
            $sortingField = 'sorting';
        }

        if ($allowedSites === null) {
            $allowedSites = [];
        } else {
            $allowedSites = GeneralUtility::intExplode(',', $allowedSites, true);
        }

        $sitesFromPid = (int)($config['sitesFromPid'] ?? 0);

        if ($sitesFromPid) {
            $allowedSites = $this->fetchAvailableRootSitesByPid($sitesFromPid);
        }

        $sites = $this->filterSites($allowedSites);
        $pages = $this->fetchPageData($sites, $config);

        if ($customSorting !== null) {
            if (!\is_a($customSorting, SiteSortingInterface::class, true)) {
                throw new \InvalidArgumentException('Invalid implementation of SiteSortingInterface');
            }
            /**
             * @var SiteSortingInterface $sorting
             */
            $sorting = GeneralUtility::makeInstance($customSorting, $sites, $pages, $sortingField);
            $sites = $sorting->sort();
        } else {
            usort($sites, static function (Site $siteA, Site $siteB) use ($pages, $sortingField) {
                // phpcs:ignore Generic.Files.LineLength
                return (int)$pages[$siteA->getRootPageId()][$sortingField] >= (int)$pages[$siteB->getRootPageId()][$sortingField] ? 1 : -1;
            });
        }

        $this->sites = $sites;
        $this->pagesData = $pages;
        $this->currentRootPage = $this->siteFinder->getSiteByPageId($siteUid);

        return $this;
    }

    /**
     * @return array<Site>
     */
    public function getSites(): array
    {
        return $this->sites;
    }

    /**
     * @return array<int, array>
     */
    public function getPages(): array
    {
        return $this->pagesData;
    }

    /**
     * @return Site
     */
    public function getCurrentRootPage(): Site
    {
        return $this->currentRootPage;
    }

    /**
     * @param array<int> $allowedSites
     * @return array<Site>
     */
    private function filterSites(array $allowedSites = []): array
    {
        $allSites = $this->siteFinder->getAllSites();

        if (count($allowedSites) === 0) {
            return array_filter($allSites, static function (Site $site) {
                return $site->getConfiguration()['headless'] ?? false;
            });
        }

        $sites = [];

        foreach ($allSites as $site) {
            if (in_array($site->getRootPageId(), $allowedSites, true) &&
                $site->getConfiguration()['headless'] ?? false) {
                $sites[] = $site;
            }
        }

        return $sites;
    }

    /**
     * @param int $pid
     * @return array<int>
     * @throws Exception
     */
    private function fetchAvailableRootSitesByPid(int $pid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $stmt = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where('is_siteroot = 1')
            ->andWhere('hidden = 0')
            ->andWhere('deleted = 0')
            ->andWhere('pid = ' . $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
            ->execute();

        $pagesData = [];

        if ($stmt instanceof Result) {
            $pagesData = $stmt->fetchAllAssociative();
        }

        return array_map(static function (array $item): int {
            return (int)$item['uid'];
        }, $pagesData);
    }

    /**
     * Fetches pages' titles & translations (if site has more than one language) of root pages
     *
     * @param array<Site> $sites
     * @param array<string, mixed> $config
     * @return array<int, array>
     * @throws Exception
     */
    private function fetchPageData(array $sites, array $config = []): array
    {
        $rootPagesId = array_values(array_map(static function (Site $item) {
            return $item->getRootPageId();
        }, $sites));

        $columns = GeneralUtility::trimExplode(',', $config['dbColumns'] ?? 'uid,title,sorting', true);

        if (count($columns) === 0) {
            $columns = ['uid', 'title', 'sorting'];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $pagesData = [];
        $stmt = $queryBuilder
            ->select(...$columns)
            ->from('pages')
            ->where('uid IN (' . $queryBuilder->createNamedParameter($rootPagesId, Connection::PARAM_INT_ARRAY) . ')')
            ->execute();

        if ($stmt instanceof Result) {
            $pagesData = $stmt->fetchAllAssociative();
        }

        $pages = [];

        foreach ($pagesData as $page) {
            $pages[(int)$page['uid']] = $page;
        }

        return $pages;
    }
}
