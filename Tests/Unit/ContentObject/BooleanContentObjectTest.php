<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class BooleanContentObjectTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function renderTest()
    {
        $booleanContentObject = new BooleanContentObject();
        self::assertFalse($booleanContentObject->render());
    }

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function renderWithProviderTest($argument, bool $result)
    {
        $booleanContentObject = new BooleanContentObject();
        self::assertEquals($result, $booleanContentObject->render($argument));
    }

    public function dataProvider(): array
    {
        return [
            ['test', false],
            [['value.' => ''], false],
            [['test' => 1], false],
            [['value' => 0], false],
            [['value' => 1], true],
        ];
    }
}
