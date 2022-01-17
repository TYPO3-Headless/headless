<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\FloatContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FloatContentObjectTest extends UnitTestCase
{
    /**
     * @test
     */
    public function renderTest()
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $contentObject = new FloatContentObject($cObj);
        self::assertEquals(0.0, $contentObject->render());
    }

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function renderWithProviderTest($argument, float $result)
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $contentObject = new FloatContentObject($cObj);
        self::assertEquals($result, $contentObject->render($argument));
    }

    public function dataProvider(): array
    {
        return [
            ['test', 0.0],
            [['value.' => ''], 0.0],
            [['test' => 1], 0.0],
            [['value' => 0], 0.0],
            [['value' => 1], 1.0],
            [['value' => 12.34], 12.34],
            [['value' => '12.34'], 12.34],
            [['value' => -12.34], -12.34],
            [['value' => '-12.34'], -12.34],
        ];
    }
}
