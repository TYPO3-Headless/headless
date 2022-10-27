<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class JsonEncoderTest extends UnitTestCase
{
    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;
    }

    /**
     * @param $testValue
     * @param $expectedValue
     *
     * @dataProvider jsonProvider
     */
    public function testEncoding($testValue, $expectedValue): void
    {
        $encoder = GeneralUtility::makeInstance(JsonEncoder::class);

        self::assertSame($expectedValue, $encoder->encode($testValue));
    }

    public function jsonProvider(): array
    {
        return [
            [[], '[]'],
            [new \stdClass(), '{}'],
            ["\xB1\x31", '[]'], // exception caught, return empty array instead
        ];
    }
}
