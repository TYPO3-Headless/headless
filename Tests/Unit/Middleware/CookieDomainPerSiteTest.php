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
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CookieDomainPerSiteTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function emptyCookieDomain()
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(3)->willReturn([
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

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $siteFinder->getAllSites()->willReturn([
            $site,
        ]);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder->reveal(), $this->prophesize(LoggerInterface::class)->reveal());

        $request = new ServerRequest('https://test-backend-api.tld');
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

    /**
     * @test
     */
    public function cookieDomainIsSet()
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(3)->willReturn([
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

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $siteFinder->getAllSites()->willReturn([
            $site,
        ]);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        $middleware = new CookieDomainPerSite($urlUtility, $siteFinder->reveal(), $this->prophesize(LoggerInterface::class)->reveal());

        $request = new ServerRequest('https://test-backend-api.tld');
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $response = new JsonResponse([]);

        $middleware->process(
            $request,
            $this->getMockHandlerWithResponse($response)
        );

        self::assertEquals(
            '.test-backend-api.tld',
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'],
        );
    }

    protected function getMockHandlerWithResponse($response)
    {
        $handler = $this->createPartialMock(RequestHandler::class, ['handle']);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }
}
