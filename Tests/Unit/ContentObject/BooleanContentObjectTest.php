<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class BooleanContentObjectTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function renderTest()
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->setRequest(new ServerRequest());

        $contentObject = new BooleanContentObject();
        $contentObject->setRequest(new ServerRequest());
        $contentObject->setContentObjectRenderer($cObj);

        self::assertFalse($contentObject->render());
    }

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function renderWithProviderTest($argument, bool $result)
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->setRequest(new ServerRequest());
        $contentObject = new BooleanContentObject();
        $contentObject->setRequest(new ServerRequest());
        $contentObject->setContentObjectRenderer($cObj);
        self::assertEquals($result, $contentObject->render($argument));
    }

    public static function dataProvider(): array
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
