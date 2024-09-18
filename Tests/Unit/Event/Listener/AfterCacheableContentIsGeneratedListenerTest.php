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
use FriendsOfTYPO3\Headless\Seo\MetaTag\Html5MetaTagManager;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
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
        $metaHandler = new MetaHandler($this->prophesize(MetaTagManagerRegistry::class)->reveal(), $this->prophesize(EventDispatcherInterface::class)->reveal());

        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder(), $metaHandler, new HeadlessUserInt());

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::NONE));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = '';

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame('', $event->getController()->content);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::FULL));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = '';

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame('', $event->getController()->content);
    }

    public function testNotModifiedWhileValidJson(): void
    {
        $metaHandler = new MetaHandler($this->prophesize(MetaTagManagerRegistry::class)->reveal(), $this->prophesize(EventDispatcherInterface::class)->reveal());

        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder(), $metaHandler, new HeadlessUserInt());

        $content = json_encode(['someCustomPageWithoutMeta' => ['title' => 'test before event']]);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::FULL));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = $content;
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getController()->content);
    }

    public function testNotModifiedWhenUserIntContent(): void
    {
        $metaHandler = new MetaHandler($this->prophesize(MetaTagManagerRegistry::class)->reveal(), $this->prophesize(EventDispatcherInterface::class)->reveal());

        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder(), $metaHandler, new HeadlessUserInt());

        $content = json_encode(['someCustomPageWithoutMeta' => ['title' => HeadlessUserInt::NESTED . '_START<<<!--INT_SCRIPT.d53df2a300e62171a7b4882c4b88a153-->>>' . HeadlessUserInt::NESTED . '_END']]);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::FULL));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = $content;
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getController()->content);
    }

    public function testModifiedPageTitle(): void
    {
        $listenerProvider = $this->prophesize(ListenerProvider::class);
        $listenerProvider->getListenersForEvent(Argument::any())->willReturn([]);

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class, $listenerProvider->reveal());

        $metaHandler = new MetaHandler($this->prophesize(MetaTagManagerRegistry::class)->reveal(), $eventDispatcher);

        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder(), $metaHandler, new HeadlessUserInt());

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::FULL));
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = json_encode(['meta' => ['title' => 'test before event'], 'seo' => ['title' => 'test before event']]);
        $controller->cObj = $this->prophesize(ContentObjectRenderer::class)->reveal();
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleProviderManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame(json_encode(['meta' => ['title' => 'test before event'], 'seo' => ['title' => 'Modified title via PageTitleProviderManager', 'meta' => []]]), $event->getController()->content);
    }

    public function testHreflangs(): void
    {
        $event = new ModifyHrefLangTagsEvent(new ServerRequest());
        $event->setHrefLangs([
            'pl-PL' => 'https://example.com/pl',
            'en-US' => 'https://example.com/us',
            'en-UK' => 'https://example.com/uk',
        ]);

        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::any())->willReturn($event);

        $metaHandler = new MetaHandler($this->prophesize(MetaTagManagerRegistry::class)->reveal(), $eventDispatcher->reveal());

        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder(), $metaHandler, new HeadlessUserInt());

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessMode::FULL));
        $GLOBALS['TYPO3_REQUEST'] = $request->reveal();
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = json_encode(['meta' => ['title' => 'test before event'], 'seo' => ['title' => 'test before event']]);
        $controller->cObj = $this->prophesize(ContentObjectRenderer::class)->reveal();
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleProviderManager');

        $registry = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);
        $registry->registerManager('html5', Html5MetaTagManager::class);
        $manager = $registry->getManagerForProperty('generator');

        $testHook = new class () {
            public function handle(): void {}
        };

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags']['test'] = $testHook::class . '->handle';

        $manager->addProperty('generator', 'TYPO3 CMS x T3Headless', [], true, 'name');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame(json_encode(['meta' => ['title' => 'test before event'], 'seo' => ['title' => 'Modified title via PageTitleProviderManager', 'meta' => [['name' => 'generator', 'content' => 'TYPO3 CMS x T3Headless']], 'link' => [
            ['rel' => 'alternate', 'hreflang' => 'pl-PL', 'href' => 'https://example.com/pl'],
            ['rel' => 'alternate', 'hreflang' => 'en-US', 'href' => 'https://example.com/us'],
            ['rel' => 'alternate', 'hreflang' => 'en-UK', 'href' => 'https://example.com/uk'],
        ]]]), $event->getController()->content);
    }
}
