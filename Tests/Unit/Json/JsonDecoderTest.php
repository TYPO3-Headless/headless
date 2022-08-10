<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonDecoder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class JsonDecoderTest extends UnitTestCase
{
    /**
     * @param $testValue
     * @param $expectedValue
     *
     * @test
     * @dataProvider possibleJsonProvider
     */
    public function possibleFalsePositives($testValue, $expectedValue): void
    {
        $jsonDecoder = GeneralUtility::makeInstance(JsonDecoder::class);

        self::assertSame($expectedValue, $jsonDecoder->isJson($testValue));
    }

    public function possibleJsonProvider(): array
    {
        return [
            ['  "12"', false],
            [0, false],
            [1, false],
            [12, false],
            [-1, false],
            ['', false],
            [null, false],
            [0.1, false],
            ['.', false],
            ["''", false],
            ["'hello'", false],
            ['"hello2"', false],
            [true, false],
            [false, false],
            ['[]', true],
            ['""', false]
        ];
    }
}
