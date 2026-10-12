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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
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
    use ProphecyTrait;

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
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->createPartialMock(SiteFinder::class, []);

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            new UrlUtility(null, $resolver->reveal(), $siteFinder),
            $this->prophesize(LinkService::class)->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $siteFinder
        );

        self::assertInstanceOf(AfterLinkIsGeneratedListener::class, $listener);
    }

    public function test__invokeNotModifingAnything()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->createMock(SiteFinder::class);

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            new UrlUtility(null, $resolver->reveal(), $siteFinder),
            $this->prophesize(LinkService::class)->reveal(),
            new TypoLinkCodecService($this->prophesize(EventDispatcherInterface::class)->reveal()),
            $siteFinder
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
            $this->createMock(SiteFinder::class)
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
            $this->createMock(SiteFinder::class)
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

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::any())->willReturnArgument();

        $listener = new AfterLinkIsGeneratedListener(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal(),
            $linkService->reveal(),
            new TypoLinkCodecService($eventDispatcher->reveal()),
            $siteFinder
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

    public function test__invokeSkipsSecondEventOfRecordLink()
    {
        $logger = $this->prophesize(Logger::class);
        $logger->error(Argument::any())->shouldNotBeCalled();
        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->withRequest(Argument::any())->shouldNotBeCalled();

        $listener = $this->createListenerForRecordLinks($logger->reveal(), $urlUtility->reveal());

        // The content object of the caller does not hold the record, so "field:pid" is empty there
        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->stdWrap(Argument::cetera())->willReturn('');
        $cObj->stdWrapValue(Argument::cetera())->willReturn('');
        $cObj->getRequest()->willReturn(new ServerRequest());

        $linkResult = $this->createPageLinkResultOfRecordLink();
        $event = new AfterLinkIsGeneratedEvent(
            $linkResult,
            $cObj->reveal(),
            ['parameter' => 't3://record?identifier=tx_news&uid=1']
        );
        $listener($event);

        self::assertSame($linkResult, $event->getLinkResult());
    }

    public function test__invokeSkipsSecondEventOfRecordLinkFromStdWrap()
    {
        $logger = $this->prophesize(Logger::class);
        $logger->error(Argument::any())->shouldNotBeCalled();
        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->withRequest(Argument::any())->shouldNotBeCalled();

        $listener = $this->createListenerForRecordLinks($logger->reveal(), $urlUtility->reveal());

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->stdWrap('', ['data' => 'parameters:href'])->willReturn('t3://record?identifier=tx_news&uid=1');
        $cObj->stdWrap(Argument::cetera())->willReturn('');
        $cObj->stdWrapValue(Argument::cetera())->willReturn('');
        $cObj->getRequest()->willReturn(new ServerRequest());

        $linkResult = $this->createPageLinkResultOfRecordLink();
        $event = new AfterLinkIsGeneratedEvent(
            $linkResult,
            $cObj->reveal(),
            ['parameter' => '', 'parameter.' => ['data' => 'parameters:href']]
        );
        $listener($event);

        self::assertSame($linkResult, $event->getLinkResult());
    }

    public function test__invokeModifiesFirstEventOfRecordLink()
    {
        $site = new Site('test', 1, []);
        $request = (new ServerRequest())->withAttribute('site', $site);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());
        $urlUtility->getFrontendUrlWithSite(
            Argument::is('https://typo3.tld/news/record'),
            Argument::is($site),
            Argument::is('frontendBase')
        )->willReturn('https://front.typo3.tld/news/record');

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->expects(self::once())->method('getSiteByPageId')->with(5)->willReturn($site);

        $listener = $this->createListenerForRecordLinks(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal(),
            $siteFinder
        );

        // The nested link generation of DatabaseRecordLinkBuilder uses a content object that holds the record
        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->stdWrap('', ['data' => 'field:pid'])->willReturn('5');
        $cObj->getRequest()->willReturn($request);

        $linkResult = $this->createPageLinkResultOfRecordLink();
        $event = new AfterLinkIsGeneratedEvent(
            $linkResult,
            $cObj->reveal(),
            $this->getNestedLinkInstructionsOfRecordLink()
        );
        $listener($event);

        self::assertSame('https://front.typo3.tld/news/record', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifiesLinkWithUnknownLinkHandler()
    {
        $site = new Site('test', 1, []);
        $request = (new ServerRequest())->withAttribute('site', $site);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal());
        $urlUtility->getFrontendUrlForPage(Argument::is('/'), Argument::is(2))
            ->willReturn('https://frontend-domain.tld/page');

        $listener = $this->createListenerForRecordLinks(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal()
        );

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->getRequest()->willReturn($request);

        $linkResult = (new LinkResult('page', '/'))->withLinkConfiguration(['parameter' => 2]);
        $event = new AfterLinkIsGeneratedEvent(
            $linkResult,
            $cObj->reveal(),
            ['parameter' => 't3://recordx?identifier=tx_news&uid=1']
        );
        $listener($event);

        self::assertSame('https://frontend-domain.tld/page', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifiesRecordLinkWithoutNestedLinkGeneration()
    {
        $site = new Site('test', 1, []);
        $request = (new ServerRequest())->withAttribute('site', $site);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->withRequest($request)->willReturn($urlUtility->reveal())->shouldBeCalledOnce();
        $urlUtility->getFrontendUrlWithSite(Argument::cetera())->willReturn('https://front.typo3.tld/news/record');

        $listener = $this->createListenerForRecordLinks(
            $this->prophesize(Logger::class)->reveal(),
            $urlUtility->reveal()
        );

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->getRequest()->willReturn($request);

        // A custom link builder for records that creates the page link itself and keeps the link instructions
        $linkInstructions = ['parameter' => 't3://record?identifier=tx_news&uid=1'];
        $linkResult = (new LinkResult('page', 'https://typo3.tld/news/record'))
            ->withLinkText('News record')
            ->withLinkConfiguration($linkInstructions);
        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), $linkInstructions);
        $listener($event);

        self::assertSame('https://front.typo3.tld/news/record', $event->getLinkResult()->getUrl());
    }

    private function createListenerForRecordLinks(
        Logger $logger,
        UrlUtility $urlUtility,
        ?SiteFinder $siteFinder = null
    ): AfterLinkIsGeneratedListener {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::any())->willReturnArgument();

        $linkService = $this->prophesize(LinkService::class);
        $linkService->resolve('t3://record?identifier=tx_news&uid=1')
            ->willReturn(['type' => LinkService::TYPE_RECORD, 'identifier' => 'tx_news', 'uid' => 1]);
        $linkService->resolve('t3://recordx?identifier=tx_news&uid=1')
            ->willThrow(new UnknownLinkHandlerException('LinkHandler for recordx was not registered', 1460581769));
        $linkService->resolve('5')->willReturn(['type' => LinkService::TYPE_PAGE, 'pageuid' => 5]);
        $linkService->resolve('')->willReturn([]);

        return new AfterLinkIsGeneratedListener(
            $logger,
            $urlUtility,
            $linkService->reveal(),
            new TypoLinkCodecService($eventDispatcher->reveal()),
            $siteFinder ?? $this->createMock(SiteFinder::class)
        );
    }

    /**
     * The link instructions DatabaseRecordLinkBuilder passes to the nested link generation, from "config.recordLinks"
     */
    private function getNestedLinkInstructionsOfRecordLink(): array
    {
        return [
            'parameter' => '',
            'parameter.' => ['data' => 'field:pid'],
            'additionalParams.' => ['data' => 'field:uid'],
        ];
    }

    /**
     * The page link the nested link generation creates, PageLinkBuilder adds "additionalParams" to the configuration
     */
    private function createPageLinkResultOfRecordLink(): LinkResult
    {
        return (new LinkResult('page', 'https://typo3.tld/news/record'))
            ->withLinkText('News record')
            ->withLinkConfiguration(array_merge(
                $this->getNestedLinkInstructionsOfRecordLink(),
                ['additionalParams' => '']
            ));
    }
}
