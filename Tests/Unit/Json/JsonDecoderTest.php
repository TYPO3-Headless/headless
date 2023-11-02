<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonDecoder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function array_fill;
use function json_decode;
use function json_encode;

class JsonDecoderTest extends UnitTestCase
{
    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;
        parent::setUp();
    }

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

    public function testDecoding(): void
    {
        $jsonDecoder = GeneralUtility::makeInstance(JsonDecoder::class);

        $class = new \stdClass();
        $class->test = 1;
        $class->testProp = true;

        $array = array_fill(0, 10, '1');

        $value = ['test' => $class, 'array' => $array];

        $encoded = json_encode($value);

        self::assertEquals([json_decode($encoded)], $jsonDecoder->decode([$encoded]));

        $value = ['test' => ['test' => 1]];
        self::assertEquals($value, $jsonDecoder->decode($value));

        $value = 123;
        $encoded = json_encode($value);
        self::assertEquals([json_decode($encoded)], $jsonDecoder->decode([$value]));

        $value = ['teststring'];
        $encoded = json_encode($value);
        self::assertEquals(json_decode($encoded), $jsonDecoder->decode(['teststring']));
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
            ['""', false],
        ];
    }
}
