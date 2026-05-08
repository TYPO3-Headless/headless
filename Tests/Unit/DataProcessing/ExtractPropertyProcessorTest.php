<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use Exception;
use FriendsOfTYPO3\Headless\DataProcessing\ExtractPropertyProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ExtractPropertyProcessorTest extends UnitTestCase
{
    public function testThrowsWhenAsMissing(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processor = new ExtractPropertyProcessor();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Please specify property 'as'");

        $processor->process($cObj, [], [], []);
    }

    public function testThrowsWhenKeyMissing(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processor = new ExtractPropertyProcessor();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Please specify property 'key'");

        $processor->process($cObj, [], ['as' => 'media'], []);
    }

    public function testExtractsTopLevelProperty(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->willReturn('media');

        $processor = new ExtractPropertyProcessor();
        $result = $processor->process($cObj, [], [
            'as' => 'media',
            'key' => 'publicUrl',
        ], [
            'publicUrl' => 'https://example.com/file.jpg',
            'other' => 'ignored',
        ]);

        self::assertSame(['media' => 'https://example.com/file.jpg'], $result);
    }

    public function testExtractsNestedProperty(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->willReturn('media');

        $processor = new ExtractPropertyProcessor();
        $result = $processor->process($cObj, [], [
            'as' => 'media',
            'key' => 'media.publicUrl',
        ], [
            'media' => [
                'publicUrl' => 'https://example.com/file.jpg',
            ],
        ]);

        self::assertSame(['media' => 'https://example.com/file.jpg'], $result);
    }

    public function testReturnsNullWhenKeyNotPresent(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->willReturn('media');

        $processor = new ExtractPropertyProcessor();
        $result = $processor->process($cObj, [], [
            'as' => 'media',
            'key' => 'missing.path',
        ], [
            'media' => ['publicUrl' => 'https://example.com/file.jpg'],
        ]);

        self::assertSame(['media' => null], $result);
    }
}
