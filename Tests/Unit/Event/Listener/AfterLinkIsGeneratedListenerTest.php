<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterLinkIsGeneratedListener;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AfterLinkIsGeneratedListenerTest extends UnitTestCase
{
    use ProphecyTrait;

    public function test__construct()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal()),
            $this->prophesize(LinkService::class)->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $this->prophesize(SiteFinder::class)->reveal()
        );

        self::assertInstanceOf(AfterLinkIsGeneratedListener::class, $listener);
    }

    public function test__invokeNotModifingAnything()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal()),
            $this->prophesize(LinkService::class)->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $this->prophesize(SiteFinder::class)->reveal()
        );

        $site = new Site('test', 1, []);
        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->getRequest()->willReturn((new ServerRequest())->withAttribute('site', $site));
        $cObj->stdWrapValue(Argument::is('ATagParams'), Argument::is([]))->willReturn('');

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('/', $event->getLinkResult()->getUrl());

        $linkResult = new LinkResult('telephone', 'tel+111222333');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('tel+111222333', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingFromPageUid()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->getFrontendUrlForPage(
            Argument::is('/'),
            Argument::is(2)
        )->willReturn('https://frontend-domain.tld/page');
        $urlUtility->getFrontendUrlWithSite(
            Argument::is('/'),
            Argument::any(),
            Argument::is('frontendBase')
        )->willReturn('https://frontend-domain.tld/page');

        $site = new Site('test', 1, []);
        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->getRequest()->willReturn($request);

        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal(),
            $this->prophesize(LinkService::class)->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $this->prophesize(SiteFinder::class)->reveal()
        );

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkConfiguration(['parameter' => 2]);
        $linkResult = $linkResult->withLinkText('t3://page?uid=2');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('https://frontend-domain.tld/page', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingExternalSite()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $site = new Site('test', 1, []);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->getFrontendUrlForPage(Argument::is('/'), Argument::is(5))->willReturn('https://front.typo3.tld');

        $linkService = $this->prophesize(LinkService::class);
        $linkService->resolve(Argument::any())->willReturn(['pageuid' => 5]);

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->getRequest()->willReturn($request);

        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal(),
            $linkService->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $this->prophesize(SiteFinder::class)->reveal()
        );
        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkConfiguration(['parameter.' => ['data' => 'parameters:href']]);
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('https://front.typo3.tld', $event->getLinkResult()->getUrl());
    }

    public function test__SitemapLink()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $site = new Site('test', 1, []);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->getFrontendUrlWithSite(
            Argument::is('https://typo3.tld/sitemap-type/pages/sitemap.xml'),
            Argument::is($site),
            Argument::is('frontendApiProxy')
        )
            ->willReturn('https://front.typo3.tld/headless/sitemap-type/pages/sitemap.xml');

        $linkService = $this->prophesize(LinkService::class);
        $linkService->resolve(Argument::any())->willReturn(['pageuid' => 5]);

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $request = (new ServerRequest())->withAttribute('site', $site);
        $cObj->getRequest()->willReturn($request);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::any())->willReturn($site);

        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::any())->willReturnArgument();

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal(),
            $linkService->reveal(),
            new TypoLinkCodecService($eventDispatcher->reveal()),
            $siteFinder->reveal()
        );

        $linkResult = new LinkResult('page', 'https://typo3.tld/sitemap-type/pages/sitemap.xml');
        $linkResult = $linkResult->withLinkConfiguration([
            'parameter' => 't3://page?uid=current&type=1533906435&sitemap=pages',
            'forceAbsoluteUrl' => true,
            'additionalParams' => '&sitemap=pages',
        ]);

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame(
            'https://front.typo3.tld/headless/sitemap-type/pages/sitemap.xml',
            $event->getLinkResult()->getUrl()
        );
    }
}
