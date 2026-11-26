<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\CategoriesProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CategoriesProcessorTest extends UnitTestCase
{
    public function testValidOrderByIsReturned(): void
    {
        $orderBy = 'title DESC, sys_category_record_mm.sorting_foreign, uid asc';

        self::assertSame($orderBy, $this->createSubject()->_call('getOrderBy', $orderBy));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOrderByProvider(): array
    {
        return [
            'injected direction' => ['title SLEEP(5)'],
            'subquery as direction' => ['title DESC, uid (SELECT 1)'],
            'trailing semicolon' => ['title DESC;'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOrderByFieldProvider(): array
    {
        return [
            'function' => ['FIND_IN_SET(uid, "1,2")'],
            'quoted field' => ['`title` DESC'],
            'subquery' => ['(SELECT 1)'],
        ];
    }

    #[DataProvider('invalidOrderByFieldProvider')]
    public function testInvalidOrderByFieldThrowsException(string $orderBy): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1795692233);

        $this->createSubject()->_call('getOrderBy', $orderBy);
    }

    #[DataProvider('invalidOrderByProvider')]
    public function testInvalidOrderByThrowsException(string $orderBy): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1795687578);

        $this->createSubject()->_call('getOrderBy', $orderBy);
    }

    private function createSubject(): CategoriesProcessor
    {
        return $this->getAccessibleMock(CategoriesProcessor::class, null, [$this->createMock(ConnectionPool::class)]);
    }
}
