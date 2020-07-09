<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\JsonDecoder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class JsonDecoderTest extends UnitTestCase
{
    /**
     * @param $testValue
     * @param $expectedValue
     *
     * @test
     * @dataProvider testValues
     */
    public function possibleFalsePositives($testValue, $expectedValue): void
    {
        $jsonDecoder = GeneralUtility::makeInstance(JsonDecoder::class);

        self::assertSame($expectedValue, $jsonDecoder->isJson($testValue));
    }

    public function testValues(): array
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
