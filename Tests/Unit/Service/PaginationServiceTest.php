<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Service;

use FriendsOfTYPO3\Headless\Service\PaginationService;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class PaginationServiceTest extends HeadlessUnitTestCase
{
    public function testFirstPageLimitsQueryWithoutOffset(): void
    {
        $modifiedObjects = $this->createMock(QueryResultInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(10);
        $query->expects(self::never())->method('setOffset');
        $query->method('execute')->willReturn($modifiedObjects);

        $result = (new PaginationService($this->getObjects(25, $query)))->paginate();

        self::assertSame($modifiedObjects, $result['objects']);
        self::assertSame(0, $result['pageId']);

        $pagination = $result['pagination'];
        self::assertSame(1, $pagination['current']);
        self::assertSame(3, $pagination['numberOfPages']);
        self::assertSame(2, $pagination['nextPage']);
        self::assertArrayNotHasKey('previousPage', $pagination);
        self::assertSame(
            [
                ['number' => 1, 'isCurrent' => true],
                ['number' => 2, 'isCurrent' => false],
                ['number' => 3, 'isCurrent' => false],
            ],
            $pagination['pages']
        );
        self::assertFalse($pagination['hasLessPages']);
        self::assertFalse($pagination['hasMorePages']);
    }

    public function testSecondPageAppliesOffset(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(10);
        $query->expects(self::once())->method('setOffset')->with(10);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $pagination = (new PaginationService($this->getObjects(25, $query)))->paginate(2)['pagination'];

        self::assertSame(2, $pagination['current']);
        self::assertSame(3, $pagination['nextPage']);
        self::assertSame(1, $pagination['previousPage']);
    }

    public function testPageBeyondRangeReturnsNullObjects(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::never())->method('execute');

        $result = (new PaginationService($this->getObjects(25, $query)))->paginate(4);

        self::assertNull($result['objects']);
    }

    public function testPageBelowOneIsCoercedToFirstPage(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $pagination = (new PaginationService($this->getObjects(25, $query)))->paginate(0)['pagination'];

        self::assertSame(1, $pagination['current']);
    }

    public function testDisplayRangeIsWindowedByMaximumNumberOfLinks(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(10);
        $query->expects(self::once())->method('setOffset')->with(50);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $pagination = (new PaginationService($this->getObjects(100, $query), 10, 5))->paginate(6)['pagination'];

        self::assertSame(4, $pagination['displayRangeStart']);
        self::assertSame(8, $pagination['displayRangeEnd']);
        self::assertCount(5, $pagination['pages']);
        self::assertTrue($pagination['hasLessPages']);
        self::assertTrue($pagination['hasMorePages']);
        self::assertSame(7, $pagination['nextPage']);
        self::assertSame(5, $pagination['previousPage']);
    }

    public function testItemsPerPageIsAtLeastOne(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $pagination = (new PaginationService($this->getObjects(3, $query), 0))->paginate()['pagination'];

        self::assertSame(3, $pagination['numberOfPages']);
        self::assertSame(['itemsPerPage' => 1, 'maximumNumberOfLinks' => 99, 'insertAbove' => false, 'insertBelow' => true], (new PaginationService($this->getObjects(3, $query), 0))->paginate()['configuration']);
    }

    public function testLastPageIsCappedByInitialLimit(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(2);
        $query->expects(self::once())->method('setOffset')->with(20);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $service = new PaginationService($this->getObjects(25, $query));
        (new ReflectionProperty($service, 'initialLimit'))->setValue($service, 22);

        $service->paginate(3);
    }

    public function testLastPageFallsBackToItemsPerPageWhenInitialLimitIsExhausted(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(10);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $service = new PaginationService($this->getObjects(25, $query));
        (new ReflectionProperty($service, 'initialLimit'))->setValue($service, 15);

        $service->paginate(3);
    }

    public function testFirstPageAppliesInitialOffset(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects(self::once())->method('setLimit')->with(10);
        $query->expects(self::once())->method('setOffset')->with(4);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $service = new PaginationService($this->getObjects(25, $query));
        (new ReflectionProperty($service, 'initialOffset'))->setValue($service, 4);

        $service->paginate();
    }

    public function testPageIdIsTakenFromRequestRouting(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->method('execute')->willReturn($this->createMock(QueryResultInterface::class));

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('routing', new PageArguments(42, '0', []));

        try {
            $result = (new PaginationService($this->getObjects(25, $query)))->paginate();
        } finally {
            unset($GLOBALS['TYPO3_REQUEST']);
        }

        self::assertSame(42, $result['pageId']);
    }

    /**
     * @return QueryResultInterface<int, object>&MockObject
     */
    private function getObjects(int $count, QueryInterface&MockObject $query): QueryResultInterface&MockObject
    {
        $objects = $this->createMock(QueryResultInterface::class);
        $objects->method('count')->willReturn($count);
        $objects->method('getQuery')->willReturn($query);

        return $objects;
    }
}
