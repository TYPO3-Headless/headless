<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchema;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SiteSchemaTest extends UnitTestCase
{
    public function testProcessReturnsActiveSiteEntries(): void
    {
        $siteA = new Site('site-a', 1, ['base' => 'https://a.tld']);
        $siteB = new Site('site-b', 2, ['base' => 'https://b.tld']);

        $provider = $this->createMock(SiteProviderInterface::class);
        $provider->method('getSites')->willReturn([$siteA, $siteB]);
        $provider->method('getPages')->willReturn([
            1 => ['uid' => 1, 'title' => 'Site A'],
            2 => ['uid' => 2, 'title' => 'Site B'],
        ]);
        $provider->method('getCurrentRootPage')->willReturn($siteA);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('getFrontendUrlForPage')->willReturnCallback(
            static fn(string $base, int $pageId): string => $base . '/page-' . $pageId
        );

        $schema = new SiteSchema($urlUtility, $this->createMock(ContentDataProcessor::class));

        $result = $schema->process($provider, ['siteUid' => 1, 'cObj' => $this->createMock(ContentObjectRenderer::class)]);

        self::assertCount(2, $result);
        self::assertSame([
            'title' => 'Site A',
            'link' => 'https://a.tld/page-1',
            'active' => 1,
            'current' => 1,
            'spacer' => 0,
        ], $result[0]);
        self::assertSame([
            'title' => 'Site B',
            'link' => 'https://b.tld/page-2',
            'active' => 0,
            'current' => 0,
            'spacer' => 0,
        ], $result[1]);
    }

    public function testProcessUsesCustomTitleField(): void
    {
        $site = new Site('site-a', 1, ['base' => 'https://a.tld']);
        $provider = $this->createMock(SiteProviderInterface::class);
        $provider->method('getSites')->willReturn([$site]);
        $provider->method('getPages')->willReturn([1 => ['uid' => 1, 'title' => 'default', 'nav_title' => 'Custom']]);
        $provider->method('getCurrentRootPage')->willReturn($site);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('getFrontendUrlForPage')->willReturn('https://a.tld/page');

        $schema = new SiteSchema($urlUtility, $this->createMock(ContentDataProcessor::class));
        $result = $schema->process($provider, [
            'cObj' => $this->createMock(ContentObjectRenderer::class),
            'processorConfiguration' => ['titleField' => 'nav_title'],
        ]);

        self::assertSame('Custom', $result[0]['title']);
    }

    public function testProcessFallsBackToDefaultTitleFieldWhenBlank(): void
    {
        $site = new Site('site-a', 1, ['base' => 'https://a.tld']);
        $provider = $this->createMock(SiteProviderInterface::class);
        $provider->method('getSites')->willReturn([$site]);
        $provider->method('getPages')->willReturn([1 => ['title' => 'Default Title']]);
        $provider->method('getCurrentRootPage')->willReturn($site);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('getFrontendUrlForPage')->willReturn('');

        $schema = new SiteSchema($urlUtility, $this->createMock(ContentDataProcessor::class));
        $result = $schema->process($provider, [
            'cObj' => $this->createMock(ContentObjectRenderer::class),
            'processorConfiguration' => ['titleField' => '   '],
        ]);

        self::assertSame('Default Title', $result[0]['title']);
    }

    public function testProcessRunsAdditionalDataProcessors(): void
    {
        $site = new Site('site-a', 1, ['base' => 'https://a.tld']);
        $provider = $this->createMock(SiteProviderInterface::class);
        $provider->method('getSites')->willReturn([$site]);
        $provider->method('getPages')->willReturn([1 => ['title' => 'A']]);
        $provider->method('getCurrentRootPage')->willReturn($site);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('getFrontendUrlForPage')->willReturn('https://a.tld/page');

        $additional = ['title' => 'A', 'extra' => 'enriched'];
        $contentDataProcessor = $this->createMock(ContentDataProcessor::class);
        $contentDataProcessor->expects(self::once())->method('process')->willReturn($additional);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::once())->method('start');

        $schema = new SiteSchema($urlUtility, $contentDataProcessor);
        $result = $schema->process($provider, [
            'cObj' => $cObj,
            'processorConfiguration' => [
                'dataProcessing.' => ['10' => 'SomeProcessor'],
            ],
        ]);

        self::assertSame($additional, $result[0]);
    }
}
