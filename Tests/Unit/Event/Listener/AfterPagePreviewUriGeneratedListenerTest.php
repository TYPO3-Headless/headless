<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterPagePreviewUriGeneratedListener;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Site\SiteFinder;

class AfterPagePreviewUriGeneratedListenerTest extends TestCase
{
    use ProphecyTrait;

    public function test__construct()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $listener = new AfterPagePreviewUriGeneratedListener(new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal()), $siteFinder->reveal());

        self::assertInstanceOf(AfterPagePreviewUriGeneratedListener::class, $listener);
    }
}
