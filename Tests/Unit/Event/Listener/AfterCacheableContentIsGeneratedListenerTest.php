<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterCacheableContentIsGeneratedListener;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Seo\MetaHandler;
use FriendsOfTYPO3\Headless\Seo\MetaHandlerInterface;
use FriendsOfTYPO3\Headless\Seo\MetaTag\Html5MetaTagManager;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class AfterCacheableContentIsGeneratedListenerTest extends UnitTestCase
{
    use ProphecyTrait;

    protected bool $resetSingletonInstances = true;

    public function testNotModifiedWithInvalidOrDisabledJsonContent(): void
    {
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(PageTitleProviderManager::class)->reveal()
        );

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::NONE));

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), '', 'abc', false);

        $listener($event);

        self::assertSame('', $event->getContent());

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), '', 'abc', false);

        $listener($event);

        self::assertSame('', $event->getContent());
    }

    public function testNotModifiedWhileValidJson(): void
    {
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(PageTitleProviderManager::class)->reveal()
        );

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $content = json_encode(['someCustomPageWithoutMeta' => ['title' => 'test before event']]);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $content, 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getContent());
    }

    public function testNotModifiedWhenUserIntContent(): void
    {
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(PageTitleProviderManager::class)->reveal()
        );

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $content = json_encode(['someCustomPageWithoutMeta' => ['title' => HeadlessUserInt::NESTED . '_START<<<!--INT_SCRIPT.d53df2a300e62171a7b4882c4b88a153-->>>' . HeadlessUserInt::NESTED . '_END']]);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $content, 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getContent());
    }

    public function testModifiedPageTitle(): void
    {
        $metaHandler = $this->prophesize(MetaHandlerInterface::class);
        $metaHandler->process(Argument::any(), Argument::any())->will(function ($args) {
            $content = $args[1];
            $content['seo']['title'] = 'Modified title via PageTitleProviderManager';
            $content['seo']['meta'] = [];
            $content['seo']['htmlAttrs'] = ['lang' => 'en', 'dir' => null];
            $content['seo']['bodyAttrs'] = ['class' => 'pid-1 layout-layout-0'];
            return $content;
        });

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler->reveal(),
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $content = json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => ['title' => 'test before event'],
            'appearance' => ['layout' => 'layout-0'],
        ]);

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $content, 'abc', false);

        $listener($event);

        self::assertSame(json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => [
                'title' => 'Modified title via PageTitleProviderManager',
                'meta' => [],
                'htmlAttrs' => ['lang' => 'en', 'dir' => null],
                'bodyAttrs' => ['class' => 'pid-1 layout-layout-0'],
            ],
            'appearance' => ['layout' => 'layout-0'],
        ]), $event->getContent());
    }

    public function testHreflangs(): void
    {
        $metaHandler = $this->prophesize(MetaHandlerInterface::class);
        $metaHandler->process(Argument::any(), Argument::any())->will(function ($args) {
            $content = $args[1];
            $content['seo']['title'] = 'Modified title via PageTitleProviderManager';
            $content['seo']['meta'] = [['name' => 'generator', 'content' => 'TYPO3 CMS x T3Headless']];
            $content['seo']['link'] = [
                ['rel' => 'alternate', 'hreflang' => 'pl-PL', 'href' => 'https://example.com/pl'],
                ['rel' => 'alternate', 'hreflang' => 'en-US', 'href' => 'https://example.com/us'],
                ['rel' => 'alternate', 'hreflang' => 'en-UK', 'href' => 'https://example.com/uk'],
            ];
            $content['seo']['htmlAttrs'] = ['lang' => 'en', 'dir' => null];
            $content['seo']['bodyAttrs'] = ['class' => 'pid-2 layout-custom custom'];
            return $content;
        });

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler->reveal(),
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $content = json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => ['title' => 'test before event'],
            'appearance' => ['layout' => 'custom'],
        ]);

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $content, 'abc', false);

        $listener($event);

        self::assertSame(json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => [
                'title' => 'Modified title via PageTitleProviderManager',
                'meta' => [['name' => 'generator', 'content' => 'TYPO3 CMS x T3Headless']],
                'link' => [
                    ['rel' => 'alternate', 'hreflang' => 'pl-PL', 'href' => 'https://example.com/pl'],
                    ['rel' => 'alternate', 'hreflang' => 'en-US', 'href' => 'https://example.com/us'],
                    ['rel' => 'alternate', 'hreflang' => 'en-UK', 'href' => 'https://example.com/uk'],
                ],
                'htmlAttrs' => ['lang' => 'en', 'dir' => null],
                'bodyAttrs' => ['class' => 'pid-2 layout-custom custom']],
            'appearance' => ['layout' => 'custom'],
        ]), $event->getContent());
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }
}
