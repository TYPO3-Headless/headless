<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\UserIntMiddleware;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class UserIntMiddlewareTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function processTest()
    {
        $middleware = new UserIntMiddleware($this->getTsfeProphecy()->reveal(), new HeadlessUserInt());

        $intScript = '<!--INT_SCRIPT.d53df2a300e62171a7b4882c4b88a153-->';
        $responseString = HeadlessUserInt::NESTED . '_START<<' . $intScript . '>>' . HeadlessUserInt::NESTED . '_END';
        $response = new HtmlResponse($responseString);

        self::assertEquals(
            $intScript,
            $middleware->process(
                new ServerRequest(),
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );

        $middleware = new UserIntMiddleware($this->getTsfeProphecy('0')->reveal(), new HeadlessUserInt());

        self::assertEquals(
            $responseString,
            $middleware->process(
                new ServerRequest(),
                $this->getMockHandlerWithResponse($response)
            )->getBody()->__toString()
        );
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    protected function getTsfeProphecy(string $staticTemplate = '1')
    {
        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = $staticTemplate;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        return $tsfe;
    }
}
