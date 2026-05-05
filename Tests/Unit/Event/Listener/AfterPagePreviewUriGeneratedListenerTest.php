<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterPagePreviewUriGeneratedListener;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AfterPagePreviewUriGeneratedListenerTest extends TestCase
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
        $siteFinder = $this->createMock(SiteFinder::class);

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver,
            $siteFinder
        ), $siteFinder, new HeadlessMode());

        self::assertInstanceOf(AfterPagePreviewUriGeneratedListener::class, $listener);
    }

    public function testLink()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);
        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willReturn($site = new Site('test', 1, ['headless' => HeadlessModeInterface::MIXED, 'frontendBase' => 'https://front.test.tld', 'base' => 'https://test.tld']));

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver,
            $siteFinder
        ), $siteFinder, new HeadlessMode());

        $event = new AfterPagePreviewUriGeneratedEvent(
            new Uri('https://test.tld/page'),
            1,
            0,
            [],
            '',
            [],
            $this->createMock(Context::class),
            []
        );

        $GLOBALS['TYPO3_REQUEST'] =  new ServerRequest();
        $listener->__invoke($event);

        self::assertSame('https://test.tld/page', (string)$event->getPreviewUri());

        $GLOBALS['BE_USER'] =  new BackendUserAuthentication();
        $listener->__invoke($event);
        self::assertSame('https://test.tld/page', (string)$event->getPreviewUri());
    }

    public function testSiteNotFound()
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);
        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException());

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver,
            $siteFinder
        ), $siteFinder, new HeadlessMode());

        $event = new AfterPagePreviewUriGeneratedEvent(
            new Uri('https://test.tld/page'),
            1,
            0,
            [],
            '',
            [],
            $this->createMock(Context::class),
            []
        );

        $GLOBALS['TYPO3_REQUEST'] =  new ServerRequest();
        $listener->__invoke($event);

        self::assertSame('https://test.tld/page', (string)$event->getPreviewUri());
    }
}
