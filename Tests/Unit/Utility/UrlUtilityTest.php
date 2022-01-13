<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Utility;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class UrlUtilityTest extends UnitTestCase
{
    use ProphecyTrait;

    public function testFrontendUrls(): void
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
                [
                    'base' => 'https://test-backend3-api.tld',
                    'condition' => 'applicationContext == "Misconfigured"',
                    'frontendBase' => 'https://test-frontend3.tld/', // added extra slash at the end
                    'frontendApiProxy' => 'https://test-frontend-api3.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api3.tld/headless/fileadmin',
                    'SpecialSitemapKey' => 'https://test-frontend3.tld/sitemap',
                ]
            ]
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(false);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame('https://test-frontend.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-api.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-api.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());
        self::assertSame('https://test-frontend.tld/sitemap', $urlUtility->resolveKey('SpecialSitemapKey'));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(false);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame('https://test-frontend2.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-api2.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-api2.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());
        self::assertSame('https://test-frontend2.tld/sitemap', $urlUtility->resolveKey('SpecialSitemapKey'));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(false);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(false);
        $resolver->evaluate(Argument::containingString('Misconfigured'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame('https://test-frontend3.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-api3.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-api3.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());
        self::assertSame('https://test-frontend3.tld/sitemap', $urlUtility->resolveKey('SpecialSitemapKey'));
    }

    public function testOptimizedUrlsForFrontendApp(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin'
                ],
                [
                    'base' => 'https://test-second-backend-api.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-second-frontend.tld',
                    'frontendApiProxy' => 'https://test-second-frontend.tld/headless',
                    'frontendFileApi' => 'https://test-second-frontend.tld/headless/fileadmin'
                ]
            ]
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(false);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // same page, so we make it relative
        self::assertSame(
            '/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-frontend.tld/test-page')
        );

        self::assertSame(
            '/test-page?some_query_param=1&some_extra=2',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-frontend.tld/test-page?some_query_param=1&some_extra=2')
        );

        // different domain, so we need absolute url
        self::assertSame(
            'https://test-second-frontend.tld/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-second-frontend.tld/test-page')
        );

        // test reversed = "Testing" condition
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(false);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // same page, so we make it relative
        self::assertSame(
            '/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-second-frontend.tld/test-page')
        );

        self::assertSame(
            '/test-page?some_query_param=1&some_extra=2',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-second-frontend.tld/test-page?some_query_param=1&some_extra=2')
        );

        // different domain, so we need absolute url
        self::assertSame(
            'https://test-frontend.tld/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-frontend.tld/test-page')
        );
    }

    public function testFrontendUrlForPage(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin'
                ]
            ]
        ]);

        $uri = new Uri('https://test-backend-api.tld');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalledOnce()->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

        // flag is enabled
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        self::assertSame(
            'https://test-frontend.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );
    }

    public function testFrontendUrlForPageWithPortsOnFrontendSide(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld:3000',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin'
                ]
            ]
        ]);

        $uri = new Uri('https://test-backend-api.tld');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalledOnce()->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

        // flag is enabled
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        self::assertSame(
            'https://test-frontend.tld:3000/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );
    }

    public function testFrontendUrlForPageWithPortsOnBothSides(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld:8000',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld:3000',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin'
                ]
            ]
        ]);

        $uri = new Uri('https://test-backend-api.tld:8000');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalledOnce()->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

        // flag is enabled
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        self::assertSame(
            'https://test-frontend.tld:3000/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld:8000/test-page', 1)
        );
    }
}
