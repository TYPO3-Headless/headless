<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\HeadlessModeSetter;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class HeadlessModeSetterTest extends UnitTestCase
{
    public function test(): void
    {
        $middleware = new HeadlessModeSetter();
        $request = new ServerRequest();
        $request = $request->withAttribute('site', new Site('test', 1, ['headless' => true]));
        $handler = $this->createMock(RequestHandler::class);
        $handler->method('handle')->willReturn(new Response());

        $middleware->process($request, $handler);

        self::assertTrue($GLOBALS['TYPO3_REQUEST']->getAttribute('headless')->getMode() === HeadlessMode::FULL);
    }
    public function testNotSet(): void
    {
        $middleware = new HeadlessModeSetter();
        $request = new ServerRequest();
        $handler = $this->createMock(RequestHandler::class);
        $handler->method('handle')->willReturn(new Response());

        $middleware->process($request, $handler);

        self::assertTrue($GLOBALS['TYPO3_REQUEST']->getAttribute('headless')->getMode() === HeadlessMode::NONE);
    }
}
