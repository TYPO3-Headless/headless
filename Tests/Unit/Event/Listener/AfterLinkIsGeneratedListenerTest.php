<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterLinkIsGeneratedListener;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AfterLinkIsGeneratedListenerTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }

    public function test__construct()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);
        $siteFinder = $this->createPartialMock(SiteFinder::class, []);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            new UrlUtility(null, $resolver, $siteFinder),
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $siteFinder
        );

        self::assertInstanceOf(AfterLinkIsGeneratedListener::class, $listener);
    }

    public function test__invokeNotModifingAnything()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);
        $siteFinder = $this->createMock(SiteFinder::class);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            new UrlUtility(null, $resolver, $siteFinder),
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $siteFinder
        );

        $site = new Site('test', 1, []);
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn((new ServerRequest())->withAttribute('site', $site));
        $cObj->method('stdWrapValue')->with('ATagParams', [])->willReturn('');

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('/', $event->getLinkResult()->getUrl());

        $linkResult = new LinkResult('telephone', 'tel+111222333');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('tel+111222333', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingFromPageUid()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('getFrontendUrlForPage')->with('/', 2)->willReturn('https://frontend-domain.tld/page');
        $urlUtility->method('getFrontendUrlWithSite')->with('/', self::anything(), 'frontendBase')->willReturn('https://frontend-domain.tld/page');

        $site = new Site('test', 1, []);
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->method('getRequest')->willReturn($request);

        $urlUtility->method('withRequest')->with($request)->willReturn($urlUtility);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class)
        );

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkConfiguration(['parameter' => 2]);
        $linkResult = $linkResult->withLinkText('t3://page?uid=2');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://frontend-domain.tld/page', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingExternalSite()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);

        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('getFrontendUrlForPage')->with('/', 5)->willReturn('https://front.typo3.tld');

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->willReturn(['pageuid' => 5]);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->method('getRequest')->willReturn($request);

        $urlUtility->method('withRequest')->with($request)->willReturn($urlUtility);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class)
        );
        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkConfiguration(['parameter.' => ['data' => 'parameters:href']]);
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://front.typo3.tld', $event->getLinkResult()->getUrl());
    }

    public function test__SitemapLink()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);

        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('getFrontendUrlWithSite')->with(
            'https://typo3.tld/sitemap-type/pages/sitemap.xml',
            $site,
            'frontendApiProxy'
        )->willReturn('https://front.typo3.tld/headless/sitemap-type/pages/sitemap.xml');

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->willReturn(['pageuid' => 5]);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->method('getRequest')->willReturn($request);

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $urlUtility->method('withRequest')->with($request)->willReturn($urlUtility);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($eventDispatcher),
            $siteFinder
        );

        $linkResult = new LinkResult('page', 'https://typo3.tld/sitemap-type/pages/sitemap.xml');
        $linkResult = $linkResult->withLinkConfiguration([
            'parameter' => 't3://page?uid=current&type=1533906435&sitemap=pages',
            'forceAbsoluteUrl' => true,
            'additionalParams' => '&sitemap=pages',
        ]);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame(
            'https://front.typo3.tld/headless/sitemap-type/pages/sitemap.xml',
            $event->getLinkResult()->getUrl()
        );
    }

    public function testInvokeFollowsShortcutDoktype(): void
    {
        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlForPage')->with('/', 7)->willReturn('https://front.tld/page-7');

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(new ServerRequest());

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class)
        );

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkConfiguration([
            'parameter' => 1,
            'page' => ['doktype' => 4, 'shortcut' => 7],
        ]);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://front.tld/page-7', $event->getLinkResult()->getUrl());
    }

    public function testInvokeWithEmptyLinkLogsErrorWhenNoSite(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error');

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(new ServerRequest());
        $cObj->method('stdWrap')->willReturn('');

        $listener = new AfterLinkIsGeneratedListener(
            $logger,
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class)
        );

        // empty parameter triggers UnableToLinkException path in getTargetSite via resolveLinkDetails
        $linkResult = new LinkResult('page', '');
        $linkResult = $linkResult->withLinkConfiguration([
            'parameter' => '',
            'parameter.' => ['data' => 'parameters:href'],
        ]);
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        // href stays empty since no site could be resolved
        self::assertSame('', $event->getLinkResult()->getUrl());
    }

}
