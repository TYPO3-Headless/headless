<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\CookieDomainPerSite;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Http\RequestHandler;

class CookieDomainPerSiteTest extends HeadlessUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);
    }

    #[Test]
    public function emptyCookieDomain()
    {
        $site = $this->createMock(Site::class);
        $site->method('getConfiguration')->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                    'SpecialSitemapKey' => 'https://test-frontend.tld/sitemap',
                ],
                [
                    'base' => 'https://test-backend2-api.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-frontend2.tld',
                    'frontendApiProxy' => 'https://test-frontend-api2.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api2.tld/headless/fileadmin',
                    'SpecialSitemapKey' => 'https://test-frontend2.tld/sitemap',
                ],
            ],
        ]);

        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturnCallback(static fn($expr): bool => str_contains((string)$expr, 'Development'));

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getAllSites']);

        $siteFinder->method('getAllSites')->willReturn([
            $site,
        ]);

        $urlUtility = new UrlUtility($resolver, $siteFinder, new HeadlessMode());
        $urlUtility = $urlUtility->withSite($site);

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder, $this->createMock(LoggerInterface::class));

        $request = new ServerRequest('https://test-backend-api.tld', 'GET', null, [], ['HTTP_HOST' => 'test-backend-api.tld', 'HTTPS' => 'on']);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $response = new JsonResponse([]);

        $middleware->process(
            $request,
            $this->getMockHandlerWithResponse($response)
        );

        self::assertEquals(
            '',
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'],
        );
    }

    #[Test]
    public function cookieDomainIsSet()
    {
        $site = $this->createMock(Site::class);
        $site->method('getConfiguration')->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                    'SpecialSitemapKey' => 'https://test-frontend.tld/sitemap',
                    'cookieDomain' => '.test-backend-api.tld',
                ],
                [
                    'base' => 'https://test-backend2-api.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-frontend2.tld',
                    'frontendApiProxy' => 'https://test-frontend-api2.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api2.tld/headless/fileadmin',
                    'SpecialSitemapKey' => 'https://test-frontend2.tld/sitemap',
                ],
            ],
        ]);

        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturnCallback(static fn($expr): bool => str_contains((string)$expr, 'Development'));

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getAllSites']);

        $siteFinder->method('getAllSites')->willReturn([
            $site,
        ]);

        $urlUtility = new UrlUtility($resolver, $siteFinder, new HeadlessMode());
        $urlUtility = $urlUtility->withSite($site);

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder, $this->createMock(LoggerInterface::class));

        $request = new ServerRequest('https://test-backend-api.tld', 'GET', null, [], ['HTTP_HOST' => 'test-backend-api.tld', 'HTTPS' => 'on']);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $response = new JsonResponse([]);

        $before = $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? null;
        $observedCookieDomain = null;
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturnCallback(function () use (&$observedCookieDomain, $response) {
            $observedCookieDomain = $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? null;
            return $response;
        });

        $middleware->process($request, $handler);

        self::assertSame('.test-backend-api.tld', $observedCookieDomain);
        self::assertSame(
            $before,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? null,
            'cookieDomain must not persist past the middleware call',
        );
    }

    #[Test]
    public function requestWithoutNormalizedParamsIsPassedThrough(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withSite');

        $middleware = new CookieDomainPerSite(
            $urlUtility,
            $this->createMock(SiteFinder::class),
            $this->createMock(LoggerInterface::class)
        );

        $response = new JsonResponse([]);

        self::assertSame(
            $response,
            $middleware->process(new ServerRequest(), $this->getMockHandlerWithResponse($response))
        );
    }

    #[Test]
    public function sitesNotMatchingRequestHostAreSkipped(): void
    {
        $siteWithForeignHost = $this->createMock(Site::class);
        $siteWithForeignHost->method('getConfiguration')->willReturn(['base' => 'https://other.tld']);
        $siteWithForeignHost->method('getBase')->willReturn(new \TYPO3\CMS\Core\Http\Uri('https://other.tld'));

        $siteWithUnresolvableBase = $this->createMock(Site::class);
        $siteWithUnresolvableBase->method('getConfiguration')->willReturn(['baseVariants' => [['base' => 'https://x.tld']]]);
        $siteWithUnresolvableBase->method('getBase')->willReturn(new \TYPO3\CMS\Core\Http\Uri('https://x.tld'));

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$siteWithForeignHost, $siteWithUnresolvableBase]);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::once())->method('withSite')->with($siteWithUnresolvableBase)->willReturnSelf();
        $urlUtility->method('resolveKey')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder, $logger);

        $request = new ServerRequest('https://backend.tld', 'GET', null, [], ['HTTP_HOST' => 'backend.tld', 'HTTPS' => 'on']);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $response = new JsonResponse([]);

        self::assertSame(
            $response,
            $middleware->process($request, $this->getMockHandlerWithResponse($response))
        );
    }

    #[Test]
    public function cookieDomainOverridesScopesDuringHandlingAndRestoresPreviousValues(): void
    {
        $site = $this->createMock(Site::class);
        $site->method('getConfiguration')->willReturn(['baseVariants' => [['base' => 'https://backend.tld']]]);
        $site->method('getBase')->willReturn(new \TYPO3\CMS\Core\Http\Uri('https://backend.tld'));

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$site]);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withSite')->willReturnSelf();
        $urlUtility->method('resolveKey')->willReturnCallback(
            static fn(string $key): string => $key === 'base' ? 'https://backend.tld' : '.new.tld'
        );

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder, $this->createMock(LoggerInterface::class));

        $request = new ServerRequest('https://backend.tld', 'GET', null, [], ['HTTP_HOST' => 'backend.tld', 'HTTPS' => 'on']);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $response = new JsonResponse([]);
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturnCallback(static function () use ($response): JsonResponse {
            self::assertSame('.new.tld', $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain']);
            self::assertSame('.new.tld', $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain']);
            self::assertArrayNotHasKey('cookieDomain', $GLOBALS['TYPO3_CONF_VARS']['BE'] ?? []);
            return $response;
        });

        $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain'] = 'old.tld';
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'], $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain']);

        try {
            self::assertSame($response, $middleware->process($request, $handler));

            self::assertSame('old.tld', $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain']);
            self::assertArrayNotHasKey('cookieDomain', $GLOBALS['TYPO3_CONF_VARS']['SYS'] ?? []);
            self::assertArrayNotHasKey('cookieDomain', $GLOBALS['TYPO3_CONF_VARS']['BE'] ?? []);
        } finally {
            unset($GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain']);
        }
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }
}
