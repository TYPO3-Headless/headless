<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Middleware;

use FriendsOfTYPO3\Headless\Middleware\SiteBaseRedirectResolver;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Http\RequestHandler;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_decode;

class SiteBaseRedirectResolverTest extends UnitTestCase
{
    use ProphecyTrait;
    protected bool $resetSingletonInstances = true;

    public function testJsonRedirect()
    {
        $site = new Site('test', 1, [
            'base' => 'https://www.typo3.org',
            'headless' => true,
            'languages' => [
                [
                    'title' =>  'English',
                    'enabled' =>  true,
                    'languageId' =>  0,
                    'base' => '/en-us',
                    'typo3Language' =>  'default',
                    'locale' =>  'en_US.UTF-8',
                    'iso-639-1' =>  'en',
                    'navigationTitle' =>  'English',
                    'hreflang' => 'en-us',
                    'direction' =>  'ltr',
                    'flag' =>  'us',
                ],
            ]]);

        $siteFinder  = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);

        $urlUtility = GeneralUtility::makeInstance(UrlUtility::class, null, $this->prophesize(Resolver::class)->reveal(), $siteFinder);
        $container->set(UrlUtility::class, $urlUtility);

        GeneralUtility::setContainer($container);

        $resolver = new SiteBaseRedirectResolver(new HeadlessMode());

        $request = new ServerRequest();
        $request = $request->withAttribute('site', $site);

        $uri = new Uri('https://www.typo3.org/');

        $request = $request->withUri($uri);
        $request = $request->withAttribute('routing', new SiteRouteResult($uri, $site));
        $request = $request->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $response = $resolver->process($request, $this->prophesize(RequestHandler::class)->reveal());

        self::assertSame(['redirectUrl' => 'https://www.typo3.org/en-us', 'statusCode' => 307], json_decode($response->getBody()->getContents(), true));

        // language resolved
        $uri = new Uri('https://www.typo3.org/en-us/');

        $request = $request->withUri($uri);
        $request = $request->withAttribute('routing', new SiteRouteResult($uri, $site));
        $request = $request->withAttribute('language', new SiteLanguage(0, 'en', new Uri('/en-us'), ['enabled' =>  true]));

        $handler = $this->prophesize(RequestHandler::class);
        $handler->handle($request)->willReturn(new JsonResponse(['nextMiddleware' => true]));

        $response = $resolver->process($request, $handler->reveal());

        self::assertSame(['nextMiddleware' => true], json_decode($response->getBody()->getContents(), true));

        // handle initial data
        $uri = new Uri('https://www.typo3.org/en-us/?type=834');

        $request = $request->withUri($uri);
        $request = $request->withAttribute('language', null);

        $handler = $this->prophesize(RequestHandler::class);
        $handler->handle($request)->willReturn(new JsonResponse(['nextMiddleware' => true]));

        $response = $resolver->process($request, $handler->reveal());

        self::assertSame(['redirectUrl' => 'https://www.typo3.org/en-us', 'statusCode' => 307], json_decode($response->getBody()->getContents(), true));

        // handle non-headless domain
        $site = new Site('test', 1, [
            'base' => 'https://www.typo3.org',
            'headless' => false,
            'languages' => [
                [
                    'title' =>  'English',
                    'enabled' =>  true,
                    'languageId' =>  0,
                    'base' => '/en-us',
                    'typo3Language' =>  'default',
                    'locale' =>  'en_US.UTF-8',
                    'iso-639-1' =>  'en',
                    'navigationTitle' =>  'English',
                    'hreflang' => 'en-us',
                    'direction' =>  'ltr',
                    'flag' =>  'us',
                ],
            ]]);

        $siteFinder  = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());

        $urlUtility = GeneralUtility::makeInstance(UrlUtility::class, null, $this->prophesize(Resolver::class)->reveal(), $siteFinder);
        $container->set(UrlUtility::class, $urlUtility);
        $errorController = $this->prophesize(ErrorController::class);
        $errorController->pageNotFoundAction(Argument::any(), Argument::any(), Argument::any())->willReturn(new JsonResponse(['ErrorController' => true]));

        $container->set(ErrorController::class, $errorController->reveal());
        GeneralUtility::setContainer($container);

        $uri = new Uri('https://www.typo3.org/');

        $request = new ServerRequest();
        $request = $request->withUri($uri);
        $request = $request->withAttribute('site', $site);

        $handler = $this->prophesize(RequestHandler::class);
        $handler->handle($request)->willReturn(new JsonResponse(['nextMiddleware' => true]));

        $response = $resolver->process($request, $handler->reveal());

        self::assertSame(['ErrorController' => true], json_decode($response->getBody()->getContents(), true));

        $uri = new Uri('https://www.typo3rocks.org/');

        $request = new ServerRequest();
        $request = $request->withUri($uri);
        $request = $request->withAttribute('site', new NullSite());

        $handler = $this->prophesize(RequestHandler::class);
        $handler->handle($request)->willReturn(new JsonResponse(['ErrorController' => true]));

        $response = $resolver->process($request, $handler->reveal());

        self::assertSame(['ErrorController' => true], json_decode($response->getBody()->getContents(), true));
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }
}
