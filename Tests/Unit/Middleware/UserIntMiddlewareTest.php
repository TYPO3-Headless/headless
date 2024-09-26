<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\UserIntMiddleware;
use FriendsOfTYPO3\Headless\Seo\MetaHandler;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class UserIntMiddlewareTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function processTest()
    {
        $middleware = new UserIntMiddleware(new HeadlessUserInt(), new HeadlessMode(), $this->createMock(MetaHandler::class));

        $request = new ServerRequest();

        $request = $request->withAttribute('headless', new Headless(HeadlessMode::FULL));

        $intScript = '';
        $responseString = '';
        $response = new HtmlResponse($responseString);

        self::assertEquals(
            $intScript,
            $middleware->process(
                $request,
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );

        $intScript = '<!--INT_SCRIPT.d53df2a300e62171a7b4882c4b88a153-->';
        $responseString = HeadlessUserInt::NESTED . '_START<<' . $intScript . '>>' . HeadlessUserInt::NESTED . '_END';
        $response = new HtmlResponse($responseString);

        self::assertEquals(
            $intScript,
            $middleware->process(
                $request,
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );

        $middleware = new UserIntMiddleware(new HeadlessUserInt(), new HeadlessMode(), $this->createMock(MetaHandler::class));

        $request = new ServerRequest();
        $request = $request->withAttribute('headless', new Headless());

        self::assertEquals(
            $responseString,
            $middleware->process(
                $request,
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );

        $request = new ServerRequest();
        $request = $request->withAttribute('headless', new Headless());

        self::assertEquals(
            $responseString,
            $middleware->process(
                $request,
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );

        $request = new ServerRequest();

        $request = $request->withAttribute('headless', new Headless(HeadlessMode::FULL));
        $request = $request->withAttribute('frontend.controller', $this->createMock(TypoScriptFrontendController::class));

        $metaHandlerMock = $this->createMock(MetaHandler::class);
        $metaHandlerMock->method('process')->withAnyParameters()->willReturn(['seo' => ['title' => 'test2']]);

        $middleware = new UserIntMiddleware(new HeadlessUserInt(), new HeadlessMode(), $metaHandlerMock);
        $c = json_encode(['seo' => ['title' => 'test']]);

        $responseString = '"' . HeadlessUserInt::STANDARD . '_START<<' . $c . '>>' . HeadlessUserInt::STANDARD . '_END"';
        $response = new HtmlResponse($responseString);

        self::assertEquals(
            json_encode(['seo' => ['title' => 'test2']]),
            $middleware->process(
                $request,
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }
}
