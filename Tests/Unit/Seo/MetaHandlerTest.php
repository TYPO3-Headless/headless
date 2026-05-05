<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Seo;

use FriendsOfTYPO3\Headless\Seo\MetaHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerInterface;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class MetaHandlerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected function setUp(): void
    {
        parent::setUp();
        GeneralUtility::setContainer(new Container());
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }

    public function testProcessBuildsSeoBlock(): void
    {
        $registry = $this->createMock(MetaTagManagerRegistry::class);
        $manager = $this->createMock(MetaTagManagerInterface::class);
        $manager->method('renderAllProperties')->willReturn(json_encode([
            ['name' => 'generator', 'content' => 'TYPO3 CMS'],
        ]));
        $registry->method('getAllManagers')->willReturn(['default' => $manager]);

        $titleProvider = $this->createMock(PageTitleProviderManager::class);
        $titleProvider->method('getTitle')->willReturn('Page Title');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $handler = $this->getMockBuilder(MetaHandler::class)
            ->setConstructorArgs([$registry, $eventDispatcher, $titleProvider, new \TYPO3\CMS\Core\TypoScript\TypoScriptService()])
            ->onlyMethods(['createContentObjectRenderer'])
            ->getMock();
        $handler->method('createContentObjectRenderer')->willReturn(
            $this->createMock(ContentObjectRenderer::class)
        );

        $request = $this->buildRequest();
        $result = $handler->process($request, ['appearance' => ['layout' => 'default']]);

        self::assertSame('Page Title', $result['seo']['title']);
        self::assertSame([['name' => 'generator', 'content' => 'TYPO3 CMS']], $result['seo']['meta']);
        self::assertSame(['lang' => 'en', 'dir' => null], $result['seo']['htmlAttrs']);
        self::assertSame(['class' => 'pid-42 layout-default'], $result['seo']['bodyAttrs']);
        self::assertArrayNotHasKey('link', $result['seo']);
    }

    public function testProcessAddsHreflangLinksWhenEventReturnsMultiple(): void
    {
        $registry = $this->createMock(MetaTagManagerRegistry::class);
        $registry->method('getAllManagers')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (ModifyHrefLangTagsEvent $event) {
            $reflection = new ReflectionProperty($event, 'hrefLangs');
            $reflection->setValue($event, [
                'en-US' => 'https://example.com/en',
                'pl-PL' => 'https://example.com/pl',
            ]);
            return $event;
        });

        $titleProvider = $this->createMock(PageTitleProviderManager::class);
        $titleProvider->method('getTitle')->willReturn('Title');

        $handler = $this->getMockBuilder(MetaHandler::class)
            ->setConstructorArgs([$registry, $eventDispatcher, $titleProvider, new \TYPO3\CMS\Core\TypoScript\TypoScriptService()])
            ->onlyMethods(['createContentObjectRenderer'])
            ->getMock();
        $handler->method('createContentObjectRenderer')->willReturn($this->createMock(ContentObjectRenderer::class));

        $result = $handler->process($this->buildRequest(), []);

        self::assertSame([
            ['rel' => 'alternate', 'hreflang' => 'en-US', 'href' => 'https://example.com/en'],
            ['rel' => 'alternate', 'hreflang' => 'pl-PL', 'href' => 'https://example.com/pl'],
        ], $result['seo']['link']);
    }

    public function testProcessOverwriteBodyTagReplacesAttributes(): void
    {
        $registry = $this->createMock(MetaTagManagerRegistry::class);
        $registry->method('getAllManagers')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $titleProvider = $this->createMock(PageTitleProviderManager::class);
        $titleProvider->method('getTitle')->willReturn('Title');

        $handler = $this->getMockBuilder(MetaHandler::class)
            ->setConstructorArgs([$registry, $eventDispatcher, $titleProvider, new \TYPO3\CMS\Core\TypoScript\TypoScriptService()])
            ->onlyMethods(['createContentObjectRenderer'])
            ->getMock();
        $handler->method('createContentObjectRenderer')->willReturn($this->createMock(ContentObjectRenderer::class));

        $request = $this->buildRequest([
            'config.' => ['headless.' => ['overwriteBodyTag' => 1]],
            'page.' => ['bodyTagAdd' => 'class="custom"'],
        ]);

        $result = $handler->process($request, ['appearance' => ['layout' => 'default']]);

        self::assertSame(['class' => 'custom'], $result['seo']['bodyAttrs']);
    }

    private function buildRequest(array $typoScriptSetup = []): ServerRequestInterface
    {
        $pageInfo = new PageInformation();
        $pageInfo->setId(42);
        $pageInfo->setPageRecord(['uid' => 42, 'title' => 'Test']);

        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupArray($typoScriptSetup);

        $language = new SiteLanguage(0, 'en_US.UTF-8', new \TYPO3\CMS\Core\Http\Uri('/'), [
            'locale' => new Locale('en'),
        ]);

        $routing = new PageArguments(42, '0', []);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(static function (string $name) use ($pageInfo, $typoScript, $language, $routing) {
            return match ($name) {
                'frontend.page.information' => $pageInfo,
                'frontend.typoscript' => $typoScript,
                'language' => $language,
                'routing' => $routing,
                default => null,
            };
        });

        return $request;
    }
}
