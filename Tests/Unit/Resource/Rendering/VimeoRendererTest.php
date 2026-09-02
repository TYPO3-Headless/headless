<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Resource\Rendering;

use FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use TYPO3\CMS\Core\Resource\FileInterface;

class VimeoRendererTest extends HeadlessUnitTestCase
{
    public function testPriorityIsHigherThanCoreRenderer(): void
    {
        self::assertSame(2, (new VimeoRenderer())->getPriority());
    }

    public function testReturnUrlOptionReturnsEscapedPlayerUrl(): void
    {
        $file = $this->createMock(FileInterface::class);

        $renderer = $this->createPartialMock(VimeoRenderer::class, ['collectOptions', 'createVimeoUrl']);
        $renderer->expects(self::once())->method('collectOptions')
            ->with(['returnUrl' => true], $file)
            ->willReturn(['returnUrl' => true, 'autoplay' => 1]);
        $renderer->expects(self::once())->method('createVimeoUrl')
            ->with(['returnUrl' => true, 'autoplay' => 1], $file)
            ->willReturn('https://player.vimeo.com/video/123?autoplay=1&loop=1');

        self::assertSame(
            'https://player.vimeo.com/video/123?autoplay=1&amp;loop=1',
            $renderer->render($file, 0, 0, ['returnUrl' => true])
        );
    }

    public function testFallsBackToCoreIframeRenderingWithoutReturnUrlOption(): void
    {
        $file = $this->createMock(FileInterface::class);

        $renderer = $this->createPartialMock(VimeoRenderer::class, ['collectOptions', 'createVimeoUrl', 'collectIframeAttributes']);
        $renderer->method('collectOptions')->willReturn(['allow' => 'fullscreen']);
        $renderer->method('createVimeoUrl')->willReturn('https://player.vimeo.com/video/123');
        $renderer->method('collectIframeAttributes')->willReturn(['allow' => 'fullscreen']);

        $html = $renderer->render($file, 640, 360);

        self::assertStringContainsString('<iframe src="https://player.vimeo.com/video/123"', $html);
        self::assertStringContainsString('allow="fullscreen"', $html);
    }
}
