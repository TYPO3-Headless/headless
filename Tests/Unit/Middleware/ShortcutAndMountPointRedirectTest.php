<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\ShortcutAndMountPointRedirect;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ShortcutAndMountPointRedirectTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function processTest()
    {
        $genericHtml = '<body>test</body>';
        $linkRedirect = 'https://test.domain2.tld';
        $genericResponse = new HtmlResponse($genericHtml);
        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '1',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_LINK, 'url' => $linkRedirect]
            )->reveal()
        );

        $correctRedirect = [
            'redirectUrl' => $linkRedirect,
            'statusCode' => 303
        ];

        $linkRedirectResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );
        $linkRedirectJson = json_decode($linkRedirectResponse->getBody()->__toString(), true);
        $this->assertEquals($correctRedirect, $linkRedirectJson);

        $initialDataResponse = $middleware->process(
            $this->getTestRequest(['type' => 834], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );

        $this->assertEquals($genericHtml, $initialDataResponse->getBody()->__toString());

        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '1',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_SHORTCUT, 'shortcut' => '5']
            )->reveal()
        );
        $shortcutJsonDecoded = [
            'redirectUrl' => '/shortcut-target',
            'statusCode' => 307
        ];

        $middlewareResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );
        $this->assertEquals($shortcutJsonDecoded, json_decode($middlewareResponse->getBody()->__toString(), true));

        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '0',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_SHORTCUT, 'shortcut' => '5']
            )->reveal()
        );

        $testRedirectResponse = new RedirectResponse('https://test.domain.tld/shortcut-target', 307);

        $middlewareResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );
        $this->assertEquals(
            $testRedirectResponse->getHeader('location')[0],
            $middlewareResponse->getHeader('location')[0]
        );
        $this->assertEquals($testRedirectResponse->getStatusCode(), $middlewareResponse->getStatusCode());

        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '0',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_LINK, 'url' => $linkRedirect]
            )->reveal()
        );

        $linkRedirectResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );

        $this->assertEquals($linkRedirect, $linkRedirectResponse->getHeader('location')[0]);
        $this->assertEquals(303, $linkRedirectResponse->getStatusCode());


        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '0',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_DEFAULT, 'url' => $linkRedirect]
            )->reveal()
        );

        $normalResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], 'https://test.domain.tld'),
            $this->getMockHandlerWithResponse($genericResponse)
        );

        $this->assertEquals($genericHtml, $normalResponse->getBody()->__toString());
    }

    public function redirectProvider(): array
    {
        $domain = 'https://test.redirect.domain.tld';
        return [
            ['user@tes@t', $domain . 'user@tes@t'],
            ['user@test.com', 'mailto:user@test.com'],
            ['/false/url:1', '/false/url:1'],
            ['/relative-url', '/relative-url'],
        ];
    }

    /**
     * @param $url
     * @param $expectedValue
     * @return void
     *
     * @test
     * @dataProvider redirectProvider
     */
    public function linkRedirectTest($url, $expectedValue): void
    {
        $domain = 'https://test.redirect.domain.tld';
        $genericHtml = '<body>test</body>';
        $genericResponse = new HtmlResponse($genericHtml);

        $middleware = new ShortcutAndMountPointRedirect(
            $this->getTsfeProphecy(
                '1',
                ['id' => 1, 'doktype' => PageRepository::DOKTYPE_LINK, 'url' => $url]
            )->reveal()
        );

        $linkRedirectResponse = $middleware->process(
            $this->getTestRequest(['type' => 0], $domain),
            $this->getMockHandlerWithResponse($genericResponse)
        );

        $correctRedirect = [
            'redirectUrl' => $expectedValue,
            'statusCode' => 303
        ];

        $linkRedirectJson = json_decode($linkRedirectResponse->getBody()->__toString(), true);
        $this->assertEquals($correctRedirect, $linkRedirectJson);
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    protected function getTestRequest(array $withQueryParams = [], string $withNormalizedParamsUrl = '')
    {
        $request = new ServerRequest();
        if ($withQueryParams !== []) {
            $request = $request->withQueryParams($withQueryParams);
        }

        if ($withNormalizedParamsUrl !== '') {
            $normalizedParams = $this->prophesize(NormalizedParams::class);
            $normalizedParams->getSiteUrl()->willReturn($withNormalizedParamsUrl);
            $request = $request->withAttribute('normalizedParams', $normalizedParams->reveal());
        }

        return $request;
    }

    protected function getTsfeProphecy(string $staticTemplate = '1', array $pageData = [])
    {
        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = $staticTemplate;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        if ($pageData === []) {
            $pageData = ['id' => 1, 'doktype' => PageRepository::DOKTYPE_LINK, 'url' => 'https://test.domain2.tld'];
        } elseif ($pageData['doktype'] === PageRepository::DOKTYPE_SHORTCUT) {
            $tsfe->getRedirectUriForShortcut(Argument::any())->willReturn('https://test.domain.tld/shortcut-target');
            $tsfe->releaseLocks()->shouldBeCalled(1);
        }

        $tsfe->page = $pageData;

        return $tsfe;
    }
}
