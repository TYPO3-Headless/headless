<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\DataProcessingTrait;
use PHPUnit\Framework\TestCase;

class DataProcessingTraitTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataProvider
     *
     * @return void
     */
    public function removeDataIfnotAppendInConfigurationTest(
        $expected,
        array $processorConfiguration,
        array $processedData
    ) {
        $trait = new class {
            use DataProcessingTrait {
                removeDataIfnotAppendInConfiguration as public;
            }
        };

        $this->assertEquals(
            $expected,

            $trait->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData)
        );
    }

    /**
     * @test
     *
     * @return void
     */
    public function removeDataIfnotAppendInConfigurationAsMenuProcessorTest()
    {
        $trait = new class {
            use DataProcessingTrait {
                removeDataIfnotAppendInConfiguration as public;
                isMenuProcessor as overwrittenMethod;
            }

            public function isMenuProcessor()
            {
                return true;
            }
        };
        $expectedResult = [
            'test' => [
                [
                    123 =>
                        ['asd' => 1],
                    'children' => [
                        ['uid' => 1],
                        ['uid' => 2],
                        ['uid' => 3],
                        ['uid' => 4],
                        ['uid' => 7, 'children' => [['uid' => 1], ['uid' => 2], ['uid' => 3]],],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expectedResult, $trait->removeDataIfnotAppendInConfiguration(
            ['appendData' => 0, 'as' => 'test'],
            [
                'test' => [
                    [
                        123 => ['asd' => 1],
                        'data' => 'test',
                        'children' => [
                            ['data' => 123, 'uid' => 1],
                            ['data' => 'asdasd', 'uid' => 2],
                            ['data' => false, 'uid' => 3],
                            ['uid' => 4],
                            [
                                'uid' => 7,
                                'children' => [
                                    ['data' => 123, 'uid' => 1],
                                    ['data' => 'asdasd', 'uid' => 2],
                                    ['data' => false, 'uid' => 3]
                                ]
                            ],
                        ]
                    ]
                ]
            ]
        ));
    }

    public function dataProvider(): array
    {
        return [
            [
                [],
                ['appendData' => 1],
                []
            ],
            [
                ['test' => []],
                ['appendData' => 0, 'as' => 'test'],
                ['test' => []]
            ],
            [
                ['test' => [[123 => 'asd']]],
                ['appendData' => 0, 'as' => 'test'],
                ['test' => [[123 => 'asd']]]
            ],
            [
                ['test' => [[123 => ['asd' => 1]]]],
                ['appendData' => 0, 'as' => 'test'],
                ['test' => [[123 => ['asd' => 1], 'data' => 'test']]]
            ]
        ];
    }
}
