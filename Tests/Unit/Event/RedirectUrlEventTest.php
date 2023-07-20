<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event;

use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RedirectUrlEventTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function eventTest()
    {
        $request = (new ServerRequest())->withAttribute('test', 1);
        $uri = new Uri('https://test.domain.tld');
        $redirectRecord = [
            'target_statuscode' => 307,
            'target' => 'https://test.domain5.tld',
        ];
        $redirectEvent = new RedirectUrlEvent($request, $uri, 'https://test.domain2.tld', 301, $redirectRecord);

        $request2 = (new ServerRequest())->withAttribute('test', 2);
        $redirectEvent->setRequest($request2);
        self::assertEquals($request2, $redirectEvent->getRequest());

        $newStatusCode = 304;
        $redirectEvent->setTargetStatusCode($newStatusCode);
        self::assertEquals($newStatusCode, $redirectEvent->getTargetStatusCode());

        $newTargetUrl = 'https://test.domain4.tld';
        $redirectEvent->setTargetUrl($newTargetUrl);
        self::assertEquals($newTargetUrl, $redirectEvent->getTargetUrl());

        self::assertFalse($redirectEvent->isPropagationStopped());

        $redirectEvent->stopPropagation();
        self::assertTrue($redirectEvent->isPropagationStopped());

        self::assertEquals($redirectRecord, $redirectEvent->getRedirectRecord());
        self::assertEquals($uri, $redirectEvent->getOriginalTargetUrl());
    }
}
