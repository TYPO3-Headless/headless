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
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
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
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
        );

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::NONE));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = '';

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame('', $event->getController()->content);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = '';

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame('', $event->getController()->content);
    }

    public function testNotModifiedWhileValidJson(): void
    {
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
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

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = $content;
        $controller->generatePageTitle($request)->willReturn('Modified title via PageTitleManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getController()->content);
    }

    public function testNotModifiedWhenUserIntContent(): void
    {
        $metaHandler = new MetaHandler(
            $this->prophesize(MetaTagManagerRegistry::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
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

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = $content;
        $controller->generatePageTitle($request)->willReturn('Modified title via PageTitleManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getController()->content);
    }

    public function testModifiedPageTitle(): void
    {
        $listenerProvider = $this->prophesize(ListenerProvider::class);
        $listenerProvider->getListenersForEvent(Argument::any())->willReturn([]);

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class, $listenerProvider->reveal());

        $metaTagRegistry = $this->prophesize(MetaTagManagerRegistry::class);
        $metaTagRegistry->getAllManagers()->willReturn([]);

        $metaHandler = new MetaHandler($metaTagRegistry->reveal(), $eventDispatcher);

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(
            0,
            'en',
            new Uri('/en'),
            []
        ));

        $request->getAttribute('routing')->willReturn(new PageArguments(1, '0', []));
        $frontendTyposcript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTyposcript->setSetupTree(new RootNode());
        $frontendTyposcript->setSetupArray([]);

        $request->getAttribute(Argument::is('frontend.typoscript'))->willReturn($frontendTyposcript);

        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => ['title' => 'test before event'],
            'appearance' => ['layout' => 'layout-0'],
        ]);
        $controller->cObj = $this->prophesize(ContentObjectRenderer::class)->reveal();
        $controller->generatePageTitle($request)->willReturn('Modified title via PageTitleProviderManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

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
        ]), $event->getController()->content);
    }

    public function testHreflangs(): void
    {
        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);

        $event = new ModifyHrefLangTagsEvent(new ServerRequest());
        $event->setHrefLangs([
            'pl-PL' => 'https://example.com/pl',
            'en-US' => 'https://example.com/us',
            'en-UK' => 'https://example.com/uk',
        ]);

        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::any())->willReturn($event);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(Argument::is('headless'))->willReturn(new Headless(HeadlessModeInterface::FULL));
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(
            0,
            'en',
            new Uri('/en'),
            []
        ));
        $request->getAttribute('routing')->willReturn(new PageArguments(2, '0', []));

        $frontendTyposcript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTyposcript->setSetupTree(new RootNode());
        $frontendTyposcript->setSetupArray(['page.' => ['bodyTagAdd' => 'class="custom"']]);

        $request->getAttribute(Argument::is('frontend.typoscript'))->willReturn($frontendTyposcript);

        $GLOBALS['TYPO3_REQUEST'] = $request->reveal();
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = json_encode([
            'meta' => ['title' => 'test before event'],
            'seo' => ['title' => 'test before event'],
            'appearance' => ['layout' => 'custom'],
        ]);
        $controller->cObj = $this->prophesize(ContentObjectRenderer::class)->reveal();
        $controller->generatePageTitle($request)->willReturn('Modified title via PageTitleProviderManager');

        $registry = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);
        $registry->registerManager('html5', Html5MetaTagManager::class);
        $manager = $registry->getManagerForProperty('generator');

        $testHook = new class () {
            public function handle(): void {}
        };

        $metaHandler = new MetaHandler(
            $registry,
            $eventDispatcher->reveal()
        );

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags']['test'] = $testHook::class . '->handle';

        $manager->addProperty('generator', 'TYPO3 CMS x T3Headless', [], true, 'name');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener = new AfterCacheableContentIsGeneratedListener(
            new JsonEncoder(),
            $metaHandler,
            new HeadlessUserInt(),
            new HeadlessMode()
        );

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
        ]), $event->getController()->content);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }
}
