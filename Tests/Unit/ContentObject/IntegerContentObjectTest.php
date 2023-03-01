<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class IntegerContentObjectTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function renderTest()
    {
        $booleanContentObject = new IntegerContentObject();
        self::assertEquals(0, $booleanContentObject->render());
    }

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function renderWithProviderTest($argument, int $result)
    {
        $booleanContentObject = new IntegerContentObject();
        self::assertEquals($result, $booleanContentObject->render($argument));
    }

    public function dataProvider(): array
    {
        return [
            ['test', 0],
            [['value.' => ''], 0],
            [['test' => 1], 0],
            [['value' => 0], 0],
            [['value' => 1], 1],
            [['value' => 1234], 1234],
            [['value' => -1234], -1234],
        ];
    }
}
