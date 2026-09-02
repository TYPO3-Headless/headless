<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Redirects;

use FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TargetUrlResolverTest extends HeadlessUnitTestCase
{
    public function testReturnsNullWhenTargetPathEqualsRequestPath(): void
    {
        $request = (new ServerRequest())->withUri(new Uri('https://test.domain.tld'));

        self::assertNull($this->getResolver()->resolve(
            ['target_statuscode' => 307, 'target' => 'https://test.domain5.tld'],
            new Uri('https://test.domain.tld'),
            $request
        ));
    }

    public function testReturnsNullWhenKeepQueryParametersIsSet(): void
    {
        $request = (new ServerRequest())->withUri(new Uri('https://test.domain3.tld/testtest'));

        self::assertNull($this->getResolver()->resolve(
            [
                'keep_query_parameters' => 1,
                'target' => 't3://page?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test',
            ],
            new Uri('https://test.domain2.tld/123/123'),
            $request
        ));
    }

    public function testReturnsNullWithoutExtbaseParamsInTarget(): void
    {
        $request = (new ServerRequest())->withUri(new Uri('https://test.domain3.tld'));

        self::assertNull($this->getResolver()->resolve(
            ['target_statuscode' => 307, 'target' => 'https://test.domain5.tld'],
            new Uri('https://test.domain.tld/testtest'),
            $request
        ));
    }

    public function testRebuildsUrlFromAdditionalParams(): void
    {
        $this->setUpContainer();

        $expectedUri = new Uri('https://test.domain2.tld/123&tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123');

        $pageRouter = $this->createMock(PageRouter::class);
        $pageRouter->expects(self::once())->method('generateUri')->with(
            '1',
            [
                'tx_test' => [
                    'action' => 'test',
                    'controller' => 'Test',
                    'test' => '123',
                ],
            ]
        )->willReturn($expectedUri);

        $request = (new ServerRequest())
            ->withUri(new Uri('https://test.domain3.tld/testtest'))
            ->withAttribute('site', $this->getSiteWithRouter($pageRouter));

        $resolvedUri = $this->getResolver()->resolve(
            [
                'target_statuscode' => 307,
                'target' => 't3://page?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123',
            ],
            new Uri('https://test.domain2.tld/123/123'),
            $request
        );

        self::assertSame($expectedUri, $resolvedUri);
    }

    public function testRebuildsUrlWithLanguageFromLegacyLParameter(): void
    {
        $this->setUpContainer();

        $expectedUri = new Uri('https://test.domain2.tld/123&tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123');
        $language = $this->createMock(SiteLanguage::class);

        $pageRouter = $this->createMock(PageRouter::class);
        $pageRouter->expects(self::once())->method('generateUri')->with(
            '1',
            [
                'tx_test' => [
                    'action' => 'test',
                    'controller' => 'Test',
                    'test' => '123',
                ],
                '_language' => $language,
            ]
        )->willReturn($expectedUri);

        $request = (new ServerRequest())
            ->withUri(new Uri('https://test.domain3.tld'))
            ->withAttribute('site', $this->getSiteWithRouter($pageRouter, $language));

        $resolvedUri = $this->getResolver()->resolve(
            [
                'target_statuscode' => 307,
                'target' => 't3://page?uid=1&L=1 - - - tx_test[action]=test&tx_test[controller]=Test&tx_test[test]=123',
            ],
            new Uri('https://test.domain2.tld/123/123'),
            $request
        );

        self::assertSame($expectedUri, $resolvedUri);
    }

    public function testLogsAndReturnsNullWhenUrlCannotBeRebuilt(): void
    {
        $this->setUpContainer();

        $site = $this->createMock(Site::class);
        $site->method('getLanguageById')->willThrowException(new InvalidArgumentException('test'));

        $request = (new ServerRequest())
            ->withUri(new Uri('https://test.domain3.tld'))
            ->withAttribute('site', $site);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        self::assertNull($this->getResolver($logger)->resolve(
            [
                'target_statuscode' => 307,
                'target' => 't3://page?uid=1&L=1 - - - tx_test[action]=test&tx_test[controller]=Test',
            ],
            new Uri('https://test.domain2.tld/123/123'),
            $request
        ));
    }

    protected function getResolver(?LoggerInterface $logger = null): TargetUrlResolver
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new TargetUrlResolver(
            new TypoLinkCodecService($eventDispatcher),
            new LinkService(),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    protected function getSiteWithRouter(PageRouter $pageRouter, ?SiteLanguage $language = null): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getRouter')->willReturn($pageRouter);
        $site->method('getLanguageById')->willReturn($language ?? $this->createMock(SiteLanguage::class));

        return $site;
    }

    public function testReturnsNullWithoutSiteAttribute(): void
    {
        $this->setUpContainer();

        $request = (new ServerRequest())->withUri(new Uri('https://test.domain3.tld/testtest'));

        self::assertNull($this->getResolver()->resolve(
            ['target' => 't3://page?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test'],
            new Uri('https://test.domain2.tld/123'),
            $request
        ));
    }

    public function testReturnsNullWhenTargetLinkCannotBeResolved(): void
    {
        $this->setUpContainer();

        $request = (new ServerRequest())
            ->withUri(new Uri('https://test.domain3.tld/testtest'))
            ->withAttribute('site', $this->createMock(Site::class));

        self::assertNull($this->getResolver()->resolve(
            ['target' => 't3://unknownhandler?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test'],
            new Uri('https://test.domain2.tld/123'),
            $request
        ));
    }

    public function testReturnsNullForNonPageTargets(): void
    {
        $this->setUpContainer();

        $request = (new ServerRequest())
            ->withUri(new Uri('https://test.domain3.tld/testtest'))
            ->withAttribute('site', $this->createMock(Site::class));

        self::assertNull($this->getResolver()->resolve(
            ['target' => 'https://external.tld/page - - - tx_test[action]=test&tx_test[controller]=Test'],
            new Uri('https://test.domain2.tld/123'),
            $request
        ));
    }

    protected function setUpContainer(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $container = new Container();
        $container->set(EventDispatcherInterface::class, $eventDispatcher);
        GeneralUtility::setContainer($container);
    }
}
