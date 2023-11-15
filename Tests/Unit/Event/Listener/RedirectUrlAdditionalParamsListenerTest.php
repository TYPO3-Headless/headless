<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\RedirectUrlAdditionalParamsListener;
use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use FriendsOfTYPO3\Headless\XClass\Routing\PageRouter;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RedirectUrlAdditionalParamsListenerTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function invokeTest()
    {
        $listener = new RedirectUrlAdditionalParamsListener(
            new TypoLinkCodecService(),
            new LinkService(),
            $this->getUrlUtility()
        );
        $uri = new Uri('https://test.domain.tld');
        $request = (new ServerRequest())->withAttribute('test', 1)->withUri($uri);
        $redirectRecord = [
            'target_statuscode' => 307,
            'target' => 'https://test.domain5.tld'
        ];

        $redirectEvent = new RedirectUrlEvent($request, $uri, 'https://test.domain2.tld', 301, $redirectRecord);
        $newRedirectEvent = clone $redirectEvent;
        $listener->__invoke($newRedirectEvent);
        self::assertEquals($redirectEvent, $newRedirectEvent);

        $request = (new ServerRequest())->withAttribute('test', 1)->withUri(new Uri('https://test.domain3.tld'));
        $redirectRecord = [
            'target_statuscode' => 307,
            'target' => 'https://test.domain5.tld'
        ];

        $redirectEvent = new RedirectUrlEvent(
            $request,
            new Uri('https://test.domain.tld/testtest'),
            'https://test.domain2.tld',
            301,
            $redirectRecord
        );
        $newRedirectEvent = clone $redirectEvent;
        $listener->__invoke($newRedirectEvent);
        self::assertEquals($redirectEvent, $newRedirectEvent);

        $requestUri = new Uri('https://test.domain3.tld/testtest');
        $request = (new ServerRequest())
            ->withAttribute('test', 1)
            ->withUri($requestUri)
            ->withAttribute('site', $this->getSiteWithBase(new Uri('https://test.domain2.tld/123')));

        $additionalParams = 'tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123';
        $redirectRecord = [
            'target_statuscode' => 307,
            'target' => 't3://page?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123'
        ];

        $newUri = new Uri('https://test.domain2.tld/123/123');
        $targetUrl = 'https://test.domain2.tld/123';
        $redirectEvent = new RedirectUrlEvent(
            $request,
            $newUri,
            $targetUrl,
            301,
            $redirectRecord
        );
        $expectedUri = new Uri($targetUrl . '&' . $additionalParams);
        $newRedirectEvent = clone $redirectEvent;

        $pageRouter = $this->prophesize(PageRouter::class);

        $pageRouter
            ->generateUri(
                '1',
                [
                    'tx_test' =>
                        [
                            'action' => 'test',
                            'controller' => 'Test',
                            'test' => '123',
                        ],
                ]
            )
            ->shouldBeCalledOnce()
            ->willReturn($expectedUri);

        $mockListener = $this->createPartialMock(RedirectUrlAdditionalParamsListener::class, ['getPageRouterForSite']);
        $mockListener->method('getPageRouterForSite')
            ->willReturn($pageRouter->reveal());
        $mockListener->__construct(
            new TypoLinkCodecService(),
            new LinkService(),
            $this->getUrlUtility()
        );
        $mockListener->__invoke($newRedirectEvent);

        self::assertSame((string)$expectedUri, $newRedirectEvent->getTargetUrl());
    }

    /**
     * @test
     */
    public function invokeWithLanguageTest()
    {
        $targetUrl = 'https://test.domain2.tld/123';
        $additionalParams = 'tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123';
        $expectedUri = new Uri($targetUrl . '&' . $additionalParams);
        $request = (new ServerRequest())->withAttribute('test', 1)->withUri(new Uri('https://test.domain3.tld'));
        $language = $this->prophesize(SiteLanguage::class)->reveal();
        $site = $this->getSiteWithBase($expectedUri, $language);
        $request = $request->withAttribute('site', $site);

        $redirectRecord = [
            'target_statuscode' => 307,
            'target' => 't3://page?uid=1&L=1 - - - tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123'
        ];

        $newUri = new Uri('https://test.domain2.tld/123/123');

        $redirectEvent = new RedirectUrlEvent(
            $request,
            $newUri,
            $targetUrl,
            301,
            $redirectRecord
        );
        $newRedirectEvent = clone $redirectEvent;

        $pageRouter = $this->prophesize(PageRouter::class);

        $pageRouter
            ->generateUri(
                '1',
                [
                    'tx_test' =>
                        [
                            'action' => 'test',
                            'controller' => 'Test',
                            'test' => '123',
                        ],
                    '_language' => $language
                ]
            )
            ->shouldBeCalledOnce()
            ->willReturn($expectedUri);

        $mockListener = $this->createPartialMock(RedirectUrlAdditionalParamsListener::class, ['getPageRouterForSite']);
        $mockListener->method('getPageRouterForSite')
            ->willReturn($pageRouter->reveal());

        $mockListener->__construct(
            new TypoLinkCodecService(),
            new LinkService(),
            $this->getUrlUtility($site)
        );
        $mockListener->__invoke($newRedirectEvent);

        self::assertSame((string)$expectedUri, $newRedirectEvent->getTargetUrl());

        $site = $this->createPartialMock(Site::class, ['getLanguageById']);
        $site->method('getLanguageById')->willThrowException(new \InvalidArgumentException('test'));
        $request = $request->withAttribute('site', $site);

        $redirectEvent = new RedirectUrlEvent(
            $request,
            $newUri,
            $targetUrl,
            301,
            $redirectRecord
        );

        $mockListener->__invoke($redirectEvent);
    }

    protected function getSiteWithBase(UriInterface $uri, $withLanguage = null)
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld:3000',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin'
                ]
            ]
        ]);

        $site->getBase()->willReturn($uri);

        if ($withLanguage === null) {
            $withLanguage = $this->prophesize(SiteLanguage::class);
            $withLanguage->reveal();
        }

        $site->getLanguageById(Argument::any())->willReturn($withLanguage);

        return $site->reveal();
    }

    protected function getUrlUtility($site = null): UrlUtility
    {
        $uri = new Uri('https://test-backend-api.tld');

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        if ($site === null) {
            $site = $this->getSiteWithBase($uri);
        }

        $siteFinder->getSiteByPageId(Argument::is(1))->willReturn($site);

        return new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal(), null, (new HeadlessMode())->withRequest((new ServerRequest())->withAttribute('headless', new Headless())));
    }
}
