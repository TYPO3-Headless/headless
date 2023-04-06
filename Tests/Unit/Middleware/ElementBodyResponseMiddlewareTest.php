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
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
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
        $middleware = new ElementBodyResponseMiddleware(new JsonEncoder(), new HeadlessMode());

        $responseArray = ['content' => ['colPos1' => [['id' => 1]]]];
        $result = json_encode($responseArray['content']['colPos1'][0]);
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST', 0),
            $this->getMockHandlerWithResponse($response)
        );

        self::assertSame(json_encode($responseArray), $testResponse->getBody()->__toString());

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

        self::assertEquals(
            $response,
            $middleware->process(
                $this->getTestRequest(['responseElementId' => 0], 'POST', 0, false),
                $this->getMockHandlerWithResponse($response)
            )
        );

        $middleware = new ElementBodyResponseMiddleware(new JsonEncoder(), new HeadlessMode());

        $responseArray = ['content' => ['colPos2' => null, 'colPos1' => [['id' => 1]]]];
        $result = json_encode($responseArray['content']['colPos1'][0]);
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );

        self::assertSame($result, $testResponse->getBody()->__toString());

        $responseArray = ['content' => ['colPos1' => ['content' => ['colPos1' => ['test' => [['id' => 1]]]]]]];
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1, 'responseElementRecursive' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );

        self::assertSame(json_encode(['id' => 1]), $testResponse->getBody()->__toString());

        $responseArray = ['content' => [['id' => 1]]];
        $testJson = json_encode($responseArray);
        $response = new HtmlResponse($testJson);
        $testResponse = $middleware->process(
            $this->getTestRequest(['responseElementId' => 1], 'POST'),
            $this->getMockHandlerWithResponse($response)
        );

        self::assertSame(json_encode(['id' => 1]), $testResponse->getBody()->__toString());
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    protected function getTestRequest(
        array $withParsedBody = [],
        string $withMethod = '',
        int $headless = 1,
        bool $withSite = true
    ) {
        $request = new ServerRequest();
        if ($withParsedBody !== []) {
            $request = $request->withParsedBody($withParsedBody);
        }

        if ($withMethod !== '') {
            $request = $request->withMethod($withMethod);
        }

        $request = $request->withAttribute('headless', new Headless());

        if ($withSite) {
            $site = $this->prophesize(Site::class);
            $site->getConfiguration()->willReturn([
                'headless' => $headless
            ]);

            $request = $request->withAttribute('site', $site->reveal());
            $request = $request->withAttribute('headless', new Headless($headless ? HeadlessMode::FULL : HeadlessMode::NONE));
        }

        return $request;
    }
}
