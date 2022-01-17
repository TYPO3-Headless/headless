<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Middleware;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Middleware\ElementBodyResponseMiddleware;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ElementBodyResponseMiddlewareTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function processTest()
    {
        $middleware = new ElementBodyResponseMiddleware($this->getTsfeProphecy()->reveal(), new JsonEncoder());

        $responseArray = ['content' => ['colPos1' => [['id' => 1]]]];
        $result = json_encode($responseArray['content']['colPos1'][0]);
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );
        self::assertSame($result, $testResponse->getBody()->__toString());

        $response = new HtmlResponse(json_encode($responseArray));

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 0], 'POST'),
                $this->getMockHandlerWithResponse($response)
            )
        );

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 1], 'GET'),
                $this->getMockHandlerWithResponse($response)
            )
        );

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(),
                $this->getMockHandlerWithResponse($response)
            )
        );

        $responseArray = ['content' => ['colPos1' => [['id' => 2]]]];
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );
        self::assertSame(json_encode([]), $testResponse->getBody()->__toString());

        $response = new HtmlResponse(json_encode($responseArray));

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 0], 'POST'),
                $this->getMockHandlerWithResponse($response)
            )
        );

        $testStringResponse = '<body>testString</body>';
        $response = new HtmlResponse($testStringResponse);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );
        self::assertSame($testStringResponse, $testResponse->getBody()->__toString());

        $response = new HtmlResponse(json_encode($responseArray));

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 0], 'POST'),
                $this->getMockHandlerWithResponse($response)
            )
        );

        $middleware = new ElementBodyResponseMiddleware($this->getTsfeProphecy('0')->reveal(), new JsonEncoder());

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 1], 'POST'),
                $this->getMockHandlerWithResponse($response)
            )
        );
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    protected function getTestRequest(array $withParsedBody = [], string $withMethod = '')
    {
        $request = new ServerRequest();
        if ($withParsedBody !== []) {
            $request = $request->withParsedBody($withParsedBody);
        }

        if ($withMethod !== '') {
            $request = $request->withMethod($withMethod);
        }

        return $request;
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
