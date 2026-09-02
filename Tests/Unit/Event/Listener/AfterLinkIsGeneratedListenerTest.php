<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterLinkIsGeneratedListener;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
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

class AfterLinkIsGeneratedListenerTest extends HeadlessUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);
    }

    public function test__construct()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);
        $siteFinder = $this->createPartialMock(SiteFinder::class, []);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            new UrlUtility($resolver, $siteFinder, new HeadlessMode()),
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $siteFinder,
            new HeadlessMode()
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
            new UrlUtility($resolver, $siteFinder, new HeadlessMode()),
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $siteFinder,
            new HeadlessMode()
        );

        $site = new Site('test', 1, []);
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())
                ->withAttribute('site', $site)
                ->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );
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
        $request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('headless', new Headless(HeadlessMode::FULL));
        $cObj->method('getRequest')->willReturn($request);

        $urlUtility->method('withRequest')->with($request)->willReturn($urlUtility);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
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
        $request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('headless', new Headless(HeadlessMode::FULL));
        $cObj->method('getRequest')->willReturn($request);

        $urlUtility->method('withRequest')->with($request)->willReturn($urlUtility);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
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
        $request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('headless', new Headless(HeadlessMode::FULL));
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
            $siteFinder,
            new HeadlessMode()
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
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
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

    public function testListenerShortCircuitsWhenHeadlessDisabled(): void
    {
        $urlUtility = $this->createMock(UrlUtility::class);
        // The listener must not touch urlUtility when headless is off.
        $urlUtility->expects(self::never())->method('withRequest');

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects(self::never())->method('getSiteByPageId');

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            // No 'headless' attribute → defaults to NONE → isEnabled() === false.
            new ServerRequest()
        );

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $siteFinder,
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '/original'))
            ->withLinkConfiguration(['parameter' => 2])
            ->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('/original', $event->getLinkResult()->getUrl());
    }

    public function testTargetSiteFallsBackToRequestSiteForCurrentPageUid(): void
    {
        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')
            ->with('https://typo3.tld/current', $site, 'frontendBase')
            ->willReturn('https://front.tld/current');

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->with('t3://page?uid=current')->willReturn(['pageuid' => 'current']);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())
                ->withAttribute('site', $site)
                ->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($eventDispatcher),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', 'https://typo3.tld/current'))
            ->withLinkConfiguration(['parameter' => 't3://page?uid=current']);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://front.tld/current', $event->getLinkResult()->getUrl());
    }

    public function testAnchorOnlyLinkResolvesAgainstRequestSite(): void
    {
        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')
            ->with('#section1', $site, 'frontendBase')
            ->willReturn('#section1');

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())
                ->withAttribute('site', $site)
                ->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );
        $cObj->method('stdWrapValue')->with('ATagParams', self::anything())->willReturn('id="section1"');

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '#section1'))
            ->withLinkConfiguration(['parameter' => '']);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('#section1', $event->getLinkResult()->getUrl());
    }

    public function testEmptyLinkParameterWithAnchorTagParamsResolvesAsInPageLink(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->with('ATagParams', self::anything())->willReturn('id="section1"');

        $linkDetails = (new ReflectionMethod(AfterLinkIsGeneratedListener::class, 'resolveLinkDetails'))
            ->invoke($this->buildListener(), '', [], $cObj);

        self::assertSame(['type' => LinkService::TYPE_INPAGE, 'url' => '', 'typoLinkParameter' => ''], $linkDetails);
    }

    public function testEmptyLinkParameterWithoutAnchorTagParamsResolvesToNull(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->with('ATagParams', self::anything())->willReturn('class="btn"');

        $linkDetails = (new ReflectionMethod(AfterLinkIsGeneratedListener::class, 'resolveLinkDetails'))
            ->invoke($this->buildListener(), '', [], $cObj);

        self::assertNull($linkDetails);
    }

    private function buildListener(): AfterLinkIsGeneratedListener
    {
        return new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $this->createMock(UrlUtility::class),
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );
    }

    public function testInsecureSchemeIsRejectedAndLogged(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::once())->method('error');

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->expects(self::never())->method('getFrontendUrlWithSite');

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $listener = new AfterLinkIsGeneratedListener(
            $logger,
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($eventDispatcher),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '/unchanged'))
            ->withLinkConfiguration(['parameter' => 'javascript:alert(1)']);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('/unchanged', $event->getLinkResult()->getUrl());
    }

    public function testUnknownLinkHandlerIsLoggedAndKeepsUrl(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::once())->method('error');

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->willThrowException(
            new \TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException('nope')
        );

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $listener = new AfterLinkIsGeneratedListener(
            $logger,
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($eventDispatcher),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '/unchanged'))
            ->withLinkConfiguration(['parameter' => 'someunknown:thing'])
            ->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('/unchanged', $event->getLinkResult()->getUrl());
    }

    public function testAdditionalParamsSegmentIsStrippedBeforeLinkResolution(): void
    {
        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturn('https://front.tld/page-9');

        $linkService = $this->createMock(LinkService::class);
        $linkService->expects(self::once())->method('resolve')
            ->with('t3://page?uid=9')
            ->willReturn(['pageuid' => 9]);

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->with(9)->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($eventDispatcher),
            $siteFinder,
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '/page-9'))
            ->withLinkConfiguration(['parameter' => 't3://page?uid=9 - - - tx_demo[x]=1']);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://front.tld/page-9', $event->getLinkResult()->getUrl());
    }

    public function testParameterStdWrapResultOverridesTypolinkUrl(): void
    {
        $site = new Site('test', 1, []);

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturn('https://front.tld/page-12');

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->willReturnCallback(static function (string $parameter): array {
            return $parameter === 't3://page?uid=12' ? ['pageuid' => 12] : [];
        });

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->expects(self::once())->method('getSiteByPageId')->with(12)->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );
        $cObj->method('stdWrap')
            ->with('t3://page?uid=11', ['field' => 'header_link'])
            ->willReturn('t3://page?uid=12');

        $listener = new AfterLinkIsGeneratedListener(
            $this->createMock(Logger::class),
            $urlUtility,
            $linkService,
            new TypoLinkCodecService($eventDispatcher),
            $siteFinder,
            new HeadlessMode()
        );

        $linkResult = (new LinkResult('page', '/page-12'))
            ->withLinkConfiguration([
                'parameter' => 't3://page?uid=11 _blank',
                'parameter.' => ['field' => 'header_link'],
            ]);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('https://front.tld/page-12', $event->getLinkResult()->getUrl());
    }

    public function testInvokeWithEmptyLinkLogsErrorWhenNoSite(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error');

        $urlUtility = $this->createMock(UrlUtility::class);
        $urlUtility->method('withRequest')->willReturnSelf();

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->method('getRequest')->willReturn(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );
        $cObj->method('stdWrap')->willReturn('');

        $listener = new AfterLinkIsGeneratedListener(
            $logger,
            $urlUtility,
            $this->createMock(LinkService::class),
            new TypoLinkCodecService($this->createMock(EventDispatcherInterface::class)),
            $this->createMock(SiteFinder::class),
            new HeadlessMode()
        );

        $linkResult = new LinkResult('page', '');
        $linkResult = $linkResult->withLinkConfiguration([
            'parameter' => '',
            'parameter.' => ['data' => 'parameters:href'],
        ]);
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj, []);
        $listener($event);

        self::assertSame('', $event->getLinkResult()->getUrl());
    }

}
