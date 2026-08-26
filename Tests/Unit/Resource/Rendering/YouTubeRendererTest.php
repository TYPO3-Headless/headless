<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Resource\Rendering;

use FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use TYPO3\CMS\Core\Resource\FileInterface;

class YouTubeRendererTest extends HeadlessUnitTestCase
{
    public function testPriorityIsHigherThanCoreRenderer(): void
    {
        self::assertSame(2, (new YouTubeRenderer())->getPriority());
    }

    public function testReturnUrlOptionReturnsEscapedPlayerUrl(): void
    {
        $file = $this->createMock(FileInterface::class);

        $renderer = $this->createPartialMock(YouTubeRenderer::class, ['collectOptions', 'createYouTubeUrl']);
        $renderer->expects(self::once())->method('collectOptions')
            ->with(['returnUrl' => true], $file)
            ->willReturn(['returnUrl' => true, 'rel' => 0]);
        $renderer->expects(self::once())->method('createYouTubeUrl')
            ->with(['returnUrl' => true, 'rel' => 0], $file)
            ->willReturn('https://www.youtube.com/embed/abc?rel=0&mute=1');

        self::assertSame(
            'https://www.youtube.com/embed/abc?rel=0&amp;mute=1',
            $renderer->render($file, 0, 0, ['returnUrl' => true])
        );
    }

    public function testFallsBackToCoreIframeRenderingWithoutReturnUrlOption(): void
    {
        $file = $this->createMock(FileInterface::class);

        $renderer = $this->createPartialMock(YouTubeRenderer::class, ['collectOptions', 'createYouTubeUrl', 'collectIframeAttributes']);
        $renderer->method('collectOptions')->willReturn(['allow' => 'fullscreen']);
        $renderer->method('createYouTubeUrl')->willReturn('https://www.youtube.com/embed/abc');
        $renderer->method('collectIframeAttributes')->willReturn(['allow' => 'fullscreen']);

        $html = $renderer->render($file, 640, 360);

        self::assertStringContainsString('<iframe src="https://www.youtube.com/embed/abc"', $html);
    }
}
