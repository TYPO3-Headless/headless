<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Resource\Rendering;

use FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\FileUtilityInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AudioTagRendererTest extends HeadlessUnitTestCase
{
    public function testPriorityIsHigherThanCoreRenderer(): void
    {
        self::assertSame(2, (new AudioTagRenderer())->getPriority());
    }

    public function testReturnUrlOptionReturnsEscapedAbsoluteUrl(): void
    {
        $fileUtility = $this->createMock(FileUtilityInterface::class);
        $fileUtility->method('getAbsoluteUrl')
            ->with('/fileadmin/audio.mp3')
            ->willReturn('https://api.tld/fileadmin/audio.mp3?a=1&b=2');

        $container = new Container();
        $container->set(FileUtilityInterface::class, $fileUtility);
        GeneralUtility::setContainer($container);

        $file = $this->createMock(FileInterface::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/audio.mp3');

        self::assertSame(
            'https://api.tld/fileadmin/audio.mp3?a=1&amp;b=2',
            (new AudioTagRenderer())->render($file, 0, 0, ['returnUrl' => true])
        );
    }

    public function testFallsBackToCoreTagRenderingWithoutReturnUrlOption(): void
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/audio.mp3');
        $file->method('getMimeType')->willReturn('audio/mpeg');

        self::assertSame(
            '<audio controls><source src="/fileadmin/audio.mp3" type="audio/mpeg"></audio>',
            (new AudioTagRenderer())->render($file, 0, 0)
        );
    }
}
