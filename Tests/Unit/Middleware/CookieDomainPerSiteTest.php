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

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }
}
