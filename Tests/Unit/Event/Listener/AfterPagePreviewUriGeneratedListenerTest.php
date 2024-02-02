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
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class AfterPagePreviewUriGeneratedListenerTest extends TestCase
{
    use ProphecyTrait;

    public function test__construct()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver->reveal(),
            $siteFinder->reveal()
        ), $siteFinder->reveal());

        self::assertInstanceOf(AfterPagePreviewUriGeneratedListener::class, $listener);
    }

    public function testLink()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::any())->willReturn($site = new Site('test', 1, ['headless' => HeadlessMode::MIXED, 'frontendBase' => 'https://front.test.tld', 'base' => 'https://test.tld']));

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver->reveal(),
            $siteFinder->reveal()
        ), $siteFinder->reveal());

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
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::any())->willThrow(new SiteNotFoundException());

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(
            null,
            $resolver->reveal(),
            $siteFinder->reveal()
        ), $siteFinder->reveal());

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
