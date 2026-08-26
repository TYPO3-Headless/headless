<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\LanguageMenuProcessor;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\Menu\MenuContentObjectFactory;

class LanguageMenuProcessorTest extends HeadlessUnitTestCase
{
    public function testMenuDataIsStrippedWithoutAppendDataOption(): void
    {
        $processedData = [
            'data' => ['uid' => 1],
            'languagemenu' => [['title' => 'English', 'data' => ['sys_language_uid' => 0]]],
        ];

        $result = $this->getProcessor()->process(
            $this->getContentObjectRenderer(),
            [],
            ['if.' => ['isTrue' => '0']],
            $processedData
        );

        self::assertArrayNotHasKey('data', $result);
        self::assertSame([['title' => 'English']], $result['languagemenu']);
    }

    public function testMenuDataIsKeptWithAppendDataOption(): void
    {
        $processedData = [
            'data' => ['uid' => 1],
            'languagemenu' => [['title' => 'English', 'data' => ['sys_language_uid' => 0]]],
        ];

        $result = $this->getProcessor()->process(
            $this->getContentObjectRenderer(),
            [],
            ['if.' => ['isTrue' => '0'], 'appendData' => '1'],
            $processedData
        );

        self::assertSame($processedData, $result);
    }

    private function getProcessor(): LanguageMenuProcessor
    {
        return new LanguageMenuProcessor($this->createMock(MenuContentObjectFactory::class));
    }

    private function getContentObjectRenderer(): ContentObjectRenderer&MockObject
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('checkIf')->willReturn(false);
        $contentObjectRenderer->method('stdWrapValue')->willReturnCallback(
            static fn(string $key, array $config, $defaultValue = '') => $config[$key] ?? $defaultValue
        );

        return $contentObjectRenderer;
    }
}
