<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Result;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class SiteProviderTest extends HeadlessUnitTestCase
{
    public function testPrepareFiltersHeadlessSitesAndSortsBySortingField(): void
    {
        $headlessSite = new Site('headless-a', 1, ['headless' => true]);
        $nonHeadlessSite = new Site('classic', 2, []);
        $anotherHeadlessSite = new Site('headless-c', 3, ['headless' => true]);

        $siteFinder = $this->getSiteFinder([$headlessSite, $nonHeadlessSite, $anotherHeadlessSite], $headlessSite);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn(
            $this->getQueryBuilderReturningRows(['uid', 'title', 'sorting'], [[1, 'A', 20], [3, 'C', 10]])
        );

        $provider = new SiteProvider($connectionPool, $siteFinder);
        $provider->prepare(['sortingField' => ''], 1);

        self::assertSame([$anotherHeadlessSite, $headlessSite], $provider->getSites());
        self::assertSame(
            [1 => ['uid' => 1, 'title' => 'A', 'sorting' => 20], 3 => ['uid' => 3, 'title' => 'C', 'sorting' => 10]],
            $provider->getPages()
        );
        self::assertSame($headlessSite, $provider->getCurrentRootPage());
    }

    public function testPrepareRespectsAllowedSitesList(): void
    {
        $headlessSite = new Site('headless-a', 1, ['headless' => true]);
        $anotherHeadlessSite = new Site('headless-c', 3, ['headless' => true]);

        $siteFinder = $this->getSiteFinder([$headlessSite, $anotherHeadlessSite], $anotherHeadlessSite);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn(
            $this->getQueryBuilderReturningRows(['uid', 'title', 'sorting'], [[3, 'C', 10]])
        );

        $provider = new SiteProvider($connectionPool, $siteFinder);
        $provider->prepare(['allowedSites' => '3', 'dbColumns' => ','], 3);

        self::assertSame([$anotherHeadlessSite], $provider->getSites());
    }

    public function testPrepareFetchesAllowedSitesFromPid(): void
    {
        $headlessSite = new Site('headless-a', 1, ['headless' => true]);
        $anotherHeadlessSite = new Site('headless-c', 3, ['headless' => true]);

        $siteFinder = $this->getSiteFinder([$headlessSite, $anotherHeadlessSite], $anotherHeadlessSite);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->getQueryBuilderReturningRows(['uid'], [[3]]),
            $this->getQueryBuilderReturningRows(['uid', 'title', 'sorting'], [[3, 'C', 10]])
        );

        $provider = new SiteProvider($connectionPool, $siteFinder);
        $provider->prepare(['sitesFromPid' => 7], 3);

        self::assertSame([$anotherHeadlessSite], $provider->getSites());
    }

    public function testPrepareUsesCustomSortingImplementation(): void
    {
        $headlessSite = new Site('headless-a', 1, ['headless' => true]);
        $anotherHeadlessSite = new Site('headless-c', 3, ['headless' => true]);

        $siteFinder = $this->getSiteFinder([$headlessSite, $anotherHeadlessSite], $headlessSite);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn(
            $this->getQueryBuilderReturningRows(['uid', 'title', 'sorting'], [[1, 'A', 10], [3, 'C', 20]])
        );

        $provider = new SiteProvider($connectionPool, $siteFinder);
        $provider->prepare(['sortingImplementation' => TestSiteSorting::class], 1);

        self::assertSame([$anotherHeadlessSite, $headlessSite], $provider->getSites());
    }

    public function testPrepareThrowsOnInvalidSortingImplementation(): void
    {
        $siteFinder = $this->getSiteFinder([new Site('headless-a', 1, ['headless' => true])], null);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn(
            $this->getQueryBuilderReturningRows(['uid', 'title', 'sorting'], [[1, 'A', 10]])
        );

        $this->expectException(InvalidArgumentException::class);

        (new SiteProvider($connectionPool, $siteFinder))
            ->prepare(['sortingImplementation' => stdClass::class], 1);
    }

    /**
     * @param array<Site> $allSites
     */
    private function getSiteFinder(array $allSites, ?Site $currentSite): SiteFinder&MockObject
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn($allSites);

        if ($currentSite !== null) {
            $siteFinder->method('getSiteByPageId')->willReturn($currentSite);
        }

        return $siteFinder;
    }

    /**
     * @param list<string> $columnNames
     * @param list<list<mixed>> $rows
     */
    private function getQueryBuilderReturningRows(array $columnNames, array $rows): QueryBuilder&MockObject
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('field = value');
        $expressionBuilder->method('in')->willReturn('field IN (value)');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn(':param');
        $queryBuilder->method('executeQuery')->willReturn(
            new Result(new ArrayResult($columnNames, $rows), $this->createMock(DoctrineConnection::class))
        );

        return $queryBuilder;
    }
}
