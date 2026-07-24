<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\HeadlessRedirectResponseListener;
use FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Redirects\Event\RedirectWasHitEvent;

class HeadlessRedirectResponseListenerTest extends HeadlessUnitTestCase
{
    public function testListenerSkipsWhenNoSite(): void
    {
        $request = (new ServerRequest('https://backend.tld/'))
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $targetUrlResolver = $this->createMock(TargetUrlResolver::class);
        $targetUrlResolver->expects(self::never())->method('resolve');

        $listener = new HeadlessRedirectResponseListener(new HeadlessMode(), $urlUtility, $targetUrlResolver);

        $event = $this->makeEvent($request, new Uri('https://target.tld/'));
        $listener($event);

        // setResponse must NOT have been called, so the placeholder response is preserved.
        self::assertSame(200, $event->getResponse()->getStatusCode());
    }

    public function testListenerSkipsWhenHeadlessDisabled(): void
    {
        // No 'headless' attribute → mode NONE → disabled.
        $request = (new ServerRequest('https://backend.tld/'))
            ->withAttribute('site', new Site('main', 1, []));

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $targetUrlResolver = $this->createMock(TargetUrlResolver::class);
        $targetUrlResolver->expects(self::never())->method('resolve');

        $listener = new HeadlessRedirectResponseListener(new HeadlessMode(), $urlUtility, $targetUrlResolver);

        $event = $this->makeEvent($request, new Uri('https://target.tld/'));
        $listener($event);

        self::assertSame(200, $event->getResponse()->getStatusCode());
    }

    public function testListenerEmitsJsonResponseWithRewrittenUrlAndStatusCode(): void
    {
        $site = new Site('main', 1, []);
        $request = (new ServerRequest('https://backend.tld/'))
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL))
            ->withAttribute('site', $site);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->with($request)->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')
            ->with('https://backend.tld/old', $site)
            ->willReturn('https://front.tld/new');
        $urlUtility->method('prepareRelativeUrlIfPossible')
            ->with('https://front.tld/new')
            ->willReturn('/new');

        $targetUrlResolver = $this->createMock(TargetUrlResolver::class);
        $targetUrlResolver->method('resolve')->willReturn(null);

        $listener = new HeadlessRedirectResponseListener(new HeadlessMode(), $urlUtility, $targetUrlResolver);

        $event = $this->makeEvent($request, new Uri('https://backend.tld/old'), ['target_statuscode' => 301]);
        $listener($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(
            ['redirectUrl' => '/new', 'statusCode' => 301],
            json_decode((string)$response->getBody(), true)
        );
    }

    public function testListenerUsesTargetUrlFromResolver(): void
    {
        $site = new Site('main', 1, []);
        $request = (new ServerRequest('https://backend.tld/'))
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL))
            ->withAttribute('site', $site);

        $matchedRedirect = [
            'target_statuscode' => 307,
            'target' => 't3://page?uid=1 - - - tx_test[action]=test&tx_test[controller]=Test',
        ];

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')
            ->with('https://backend.tld/resolved?tx_test%5Baction%5D=test', $site)
            ->willReturn('https://front.tld/resolved?tx_test%5Baction%5D=test');
        $urlUtility->method('prepareRelativeUrlIfPossible')
            ->willReturnArgument(0);

        $targetUrl = new Uri('https://backend.tld/old');
        $resolvedUrl = new Uri('https://backend.tld/resolved?tx_test%5Baction%5D=test');

        $targetUrlResolver = $this->createMock(TargetUrlResolver::class);
        $targetUrlResolver->method('resolve')
            ->with($matchedRedirect, $targetUrl, $request)
            ->willReturn($resolvedUrl);

        $listener = new HeadlessRedirectResponseListener(new HeadlessMode(), $urlUtility, $targetUrlResolver);

        $event = $this->makeEvent($request, $targetUrl, $matchedRedirect);
        $listener($event);

        self::assertSame(
            ['redirectUrl' => 'https://front.tld/resolved?tx_test%5Baction%5D=test', 'statusCode' => 307],
            json_decode((string)$event->getResponse()->getBody(), true)
        );
    }

    /**
     * @param array<string, mixed> $matchedRedirect
     */
    private function makeEvent(
        ServerRequest $request,
        Uri $targetUrl,
        array $matchedRedirect = []
    ): RedirectWasHitEvent {
        $placeholder = $this->createMock(ResponseInterface::class);
        $placeholder->method('getStatusCode')->willReturn(200);

        return new RedirectWasHitEvent($request, $placeholder, $matchedRedirect, $targetUrl);
    }
}
