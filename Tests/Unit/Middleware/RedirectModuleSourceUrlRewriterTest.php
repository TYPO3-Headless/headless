<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\RedirectModuleSourceUrlRewriter;
use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;

class RedirectModuleSourceUrlRewriterTest extends HeadlessUnitTestCase
{
    public function testRewritesSourceUrlAttributesInModuleResponse(): void
    {
        $html = '<typo3-qrcode-modal-button class="btn" content="https://website.local/qr-source" show-download></typo3-qrcode-modal-button>'
            . '<typo3-copy-to-clipboard text="https://website.local/s/Ab12Cd34" class="btn"></typo3-copy-to-clipboard>'
            . '<button data-content="https://website.local/qr-source">delete</button>';

        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            static fn(string $host, string $path): string => 'https://front.local' . $path
        );

        $response = $this->process($resolver, $html, 'qrcodes');
        $body = (string)$response->getBody();

        self::assertStringContainsString('content="https://front.local/qr-source"', $body);
        self::assertStringContainsString('text="https://front.local/s/Ab12Cd34"', $body);
        // data-content (confirm dialogs etc.) must stay untouched.
        self::assertStringContainsString('data-content="https://website.local/qr-source"', $body);
        self::assertFalse($response->hasHeader('Content-Length'));
    }

    public function testWildcardHostIsPassedToResolver(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->expects(self::once())->method('resolve')
            ->with('*', '/qr-source', self::isInstanceOf(ServerRequestInterface::class))
            ->willReturn('https://front.local/qr-source');

        $response = $this->process($resolver, '<x content="https://*/qr-source"></x>', 'short_urls');

        self::assertStringContainsString('content="https://front.local/qr-source"', (string)$response->getBody());
    }

    public function testEscapedEntitiesAreDecodedForResolutionAndReEncoded(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->expects(self::once())->method('resolve')
            ->with('website.local', '/s/a?b=1&c=2')
            ->willReturn('https://front.local/s/a?b=1&c=2');

        $response = $this->process($resolver, '<x text="https://website.local/s/a?b=1&amp;c=2"></x>', 'qrcodes');

        self::assertStringContainsString('text="https://front.local/s/a?b=1&amp;c=2"', (string)$response->getBody());
    }

    public function testUnresolvableUrlKeepsOriginalMarkupAndResponseInstance(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->method('resolve')->willReturn(null);

        $html = '<x content="https://unknown.tld/qr-source"></x>';
        $handlerResponse = new HtmlResponse($html);

        $response = $this->getMiddleware($resolver)->process(
            $this->getModuleRequest('qrcodes'),
            $this->getHandler($handlerResponse)
        );

        self::assertSame($handlerResponse, $response);
    }

    public function testNonModuleRequestIsIgnored(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->expects(self::never())->method('resolve');

        $handlerResponse = new HtmlResponse('<x content="https://website.local/qr-source"></x>');

        $response = $this->getMiddleware($resolver)->process(
            new ServerRequest(),
            $this->getHandler($handlerResponse)
        );

        self::assertSame($handlerResponse, $response);
    }

    public function testOtherBackendModuleIsIgnored(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->expects(self::never())->method('resolve');

        $handlerResponse = new HtmlResponse('<x content="https://website.local/qr-source"></x>');

        $response = $this->getMiddleware($resolver)->process(
            $this->getModuleRequest('redirects'),
            $this->getHandler($handlerResponse)
        );

        self::assertSame($handlerResponse, $response);
    }

    public function testEmptyModuleResponseBodyIsPassedThrough(): void
    {
        $resolver = $this->createMock(SourceUrlResolver::class);
        $resolver->expects(self::never())->method('resolve');

        $handlerResponse = new HtmlResponse('');

        $response = $this->getMiddleware($resolver)->process(
            $this->getModuleRequest('qrcodes'),
            $this->getHandler($handlerResponse)
        );

        self::assertSame($handlerResponse, $response);
    }

    private function process(SourceUrlResolver $resolver, string $html, string $moduleIdentifier): HtmlResponse
    {
        $response = $this->getMiddleware($resolver)->process(
            $this->getModuleRequest($moduleIdentifier),
            $this->getHandler(new HtmlResponse($html))
        );

        self::assertInstanceOf(HtmlResponse::class, $response);

        return $response;
    }

    private function getMiddleware(SourceUrlResolver $resolver): RedirectModuleSourceUrlRewriter
    {
        return new RedirectModuleSourceUrlRewriter($resolver);
    }

    private function getModuleRequest(string $moduleIdentifier): ServerRequestInterface
    {
        $module = $this->createMock(ModuleInterface::class);
        $module->method('getIdentifier')->willReturn($moduleIdentifier);

        return (new ServerRequest())->withAttribute(
            'route',
            new Route('/module/link-management/' . $moduleIdentifier, ['module' => $module])
        );
    }

    private function getHandler(HtmlResponse $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }
}
