<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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
                ],
            ],
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

    public function testFrontendUrlsWithBaseProductionAndLocalOverride(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(3)->willReturn([
            'base' => 'https://api.typo3.org/',
            'frontendBase' => 'https://www.typo3.org/',
            'frontendApiProxy' => 'https://www.typo3.org/headless/',
            'frontendFileApi' => 'https://www.typo3.org/headless/fileadmin/',
            'SpecialSitemapKey' => 'https://www.typo3.org/custom-sitemap/',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld/',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless/',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin/',
                    'SpecialSitemapKey' => 'https://test-frontend.tld/sitemap/',
                ],
            ],
        ]);

        $siteFinder = $this->prophesize(SiteFinder::class);

        // local override
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame('https://test-frontend.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-api.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-api.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());
        self::assertSame('https://test-frontend.tld/sitemap', $urlUtility->resolveKey('SpecialSitemapKey'));

        // production only in base
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(false);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame('https://www.typo3.org', $urlUtility->getFrontendUrl());
        self::assertSame('https://www.typo3.org/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://www.typo3.org/headless/fileadmin', $urlUtility->getStorageProxyUrl());
        self::assertSame('https://www.typo3.org/custom-sitemap', $urlUtility->resolveKey('SpecialSitemapKey'));
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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
                [
                    'base' => 'https://test-second-backend-api.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-second-frontend.tld',
                    'frontendApiProxy' => 'https://test-second-frontend.tld/headless',
                    'frontendFileApi' => 'https://test-second-frontend.tld/headless/fileadmin',
                ],
            ],
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
            $urlUtility->prepareRelativeUrlIfPossible(
                'https://test-frontend.tld/test-page?some_query_param=1&some_extra=2'
            )
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
            $urlUtility->prepareRelativeUrlIfPossible(
                'https://test-second-frontend.tld/test-page?some_query_param=1&some_extra=2'
            )
        );

        // different domain, so we need absolute url
        self::assertSame(
            'https://test-frontend.tld/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-frontend.tld/test-page')
        );
        $site = $this->createMockSite('https://test-backend-api.tld:8000');
        $urlUtility = $urlUtility->withSite($site);

        self::assertSame(
            '/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('test-page')
        );

        self::assertSame(
            'test.page',
            $urlUtility->prepareRelativeUrlIfPossible('test.page')
        );
    }

    public function testLanguageResolver(): void
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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
                [
                    'base' => 'https://test-second-backend-api.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-second-frontend.tld',
                    'frontendApiProxy' => 'https://test-second-frontend.tld/headless',
                    'frontendFileApi' => 'https://test-second-frontend.tld/headless/fileadmin',
                ],
            ],
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(true);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(false);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());
        $urlUtility = $urlUtility->withLanguage(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend-from-lang.tld',
                    'frontendApiProxy' => 'https://test-frontend-from-lang.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-from-lang.tld/headless/fileadmin',
                ],
            ],
        ]));

        // same page, so we make it relative
        self::assertSame(
            '/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-frontend-from-lang.tld/test-page')
        );

        self::assertSame(
            '/test-page?some_query_param=1&some_extra=2',
            $urlUtility->prepareRelativeUrlIfPossible(
                'https://test-frontend-from-lang.tld/test-page?some_query_param=1&some_extra=2'
            )
        );

        // different domain, so we need absolute url
        self::assertSame(
            'https://test-second-frontend.tld/test-page',
            $urlUtility->prepareRelativeUrlIfPossible('https://test-second-frontend.tld/test-page')
        );

        self::assertSame('https://test-frontend-from-lang.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-from-lang.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-from-lang.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());

        // not overlay site variants if language has not defined variants
        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());
        $urlUtility = $urlUtility->withLanguage(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
        ]));

        self::assertSame('https://test-frontend.tld', $urlUtility->getFrontendUrl());

        // switch env for language
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::containingString('Development'))->willReturn(false);
        $resolver->evaluate(Argument::containingString('Testing'))->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());
        $urlUtility = $urlUtility->withLanguage(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend-from-lang.tld',
                    'frontendApiProxy' => 'https://test-frontend-from-lang.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-from-lang.tld/headless/fileadmin',
                ],
                [
                    'base' => 'https://test-backend-api-testing.tld',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test-frontend-from-lang-testing-env.tld',
                    'frontendApiProxy' => 'https://test-frontend-from-lang-testing-env.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-from-lang-testing-env.tld/headless/fileadmin',
                ],
            ],
        ]));

        self::assertSame('https://test-frontend-from-lang-testing-env.tld', $urlUtility->getFrontendUrl());
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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => false,
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(2)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

        // flag is enabled
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api23.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend23.tld',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => true,
        ]);

        $uri = new Uri('https://test-backend-api23.tld');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(1)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());

        self::assertSame(
            'https://test-frontend23.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api23.tld/test-page', 1)
        );
    }

    public function testFrontendUrlForPageWithAlreadyFrontendUrlResolved(): void
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://front.api.tld',
                ],
            ],
            'headless' => true,
        ]);

        $uri = new Uri('https://api.tld');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalledOnce()->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        self::assertSame(
            'https://front.api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://front.api.tld/test-page', 1)
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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => false,
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(2)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => true,
        ]);

        $uri = new Uri('https://test-backend-api.tld');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(2)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());

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
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => false,
        ]);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(2)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());
        $urlUtility = $urlUtility->withSite($site->reveal());

        // flag is not existing/disabled
        self::assertSame(
            'https://test-backend-api.tld/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld/test-page', 1)
        );

        // flag is enabled
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld:8000',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend.tld:3000',
                    'frontendApiProxy' => 'https://test-frontend-api.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-api.tld/headless/fileadmin',
                ],
            ],
            'headless' => true,
        ]);

        $uri = new Uri('https://test-backend-api.tld:8000');

        $site->getBase()->shouldBeCalled(2)->willReturn($uri);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalled(2)->willReturn($site->reveal());

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal());

        self::assertSame(
            'https://test-frontend.tld:3000/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld:8000/test-page', 1)
        );
    }

    public function testEdgeCases()
    {
        $site = $this->createMockSite('https://test-backend-api.tld:8000');
        $request = $this->prophesize(ServerRequest::class);
        $request->getAttribute(Argument::is('site'))->willReturn($site);
        $request->getAttribute(Argument::is('language'))->willReturn(null);

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId(Argument::is(1))->shouldBeCalledOnce()->willReturn($site);
        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal(), $request->reveal());

        self::assertSame(
            'https://test-backend-api.tld:8000/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld:8000/test-page', 1)
        );

        $siteFinder = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('test'));
        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder, $request->reveal());

        self::assertSame(
            'https://test-backend-api.tld:8000/test-page',
            $urlUtility->getFrontendUrlForPage('https://test-backend-api.tld:8000/test-page', 1)
        );

        $resolver = $this->createPartialMock(Resolver::class, ['evaluate']);
        $resolver->method('evaluate')->willThrowException(new SyntaxError('test'));

        $urlUtility = new UrlUtility(null, $resolver, $siteFinder, $request->reveal());
        self::assertSame('', $urlUtility->getFrontendUrl());

        $urlUtility = $urlUtility->withSite($this->createMockSite('https://test-frontend.tld', '', []));
        self::assertSame('', $urlUtility->getFrontendUrl());

        $request = $this->prophesize(ServerRequest::class);
        $request->getAttribute(Argument::is('site'))->willReturn($site);
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend-from-lang.tld',
                    'frontendApiProxy' => 'https://test-frontend-from-lang.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-from-lang.tld/headless/fileadmin',
                ],
            ],
        ]));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder, $request->reveal());
        self::assertSame('https://test-frontend-from-lang.tld', $urlUtility->getFrontendUrl());

        $request = $this->prophesize(ServerRequest::class);
        $request->getAttribute(Argument::is('site'))->willReturn($site);
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
        ]));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder, $request->reveal());
        self::assertSame('', $urlUtility->getFrontendUrl());

        // configuration on language lvl without variants
        $request = $this->prophesize(ServerRequest::class);
        $request->getAttribute(Argument::is('site'))->willReturn($this->createMockSite('https://test-backend-api.tld:8000', '', []));
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'frontendBase' => 'https://frontend-domain-from-lang.tld/',
            'frontendApiProxy' => 'https://frontend-domain-from-lang.tld/headless/',
            'frontendFileApi' => 'https://frontend-domain-from-lang.tld/headless/fileadmin/',
        ]));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder, $request->reveal());
        self::assertSame('https://frontend-domain-from-lang.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://frontend-domain-from-lang.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://frontend-domain-from-lang.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());

        // configuration on language lvl with variants

        $request = $this->prophesize(ServerRequest::class);
        $request->getAttribute(Argument::is('site'))->willReturn($this->createMockSite('https://test-backend-api.tld:8000', '', []));
        $request->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'frontendBase' => 'https://frontend-domain-from-lang.tld',
            'frontendApiProxy' => 'https://frontend-domain-from-lang.tld/headless/',
            'frontendFileApi' => 'https://frontend-domain-from-lang.tld/headless/fileadmin',
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend-from-when-develop-lang.tld',
                    'frontendApiProxy' => 'https://test-frontend-from-when-develop-lang.tld/headless',
                    'frontendFileApi' => 'https://test-frontend-from-when-develop-lang.tld/headless/fileadmin',
                ],
            ],
        ]));

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder, $request->reveal());
        self::assertSame('https://test-frontend-from-when-develop-lang.tld', $urlUtility->getFrontendUrl());
        self::assertSame('https://test-frontend-from-when-develop-lang.tld/headless', $urlUtility->getProxyUrl());
        self::assertSame('https://test-frontend-from-when-develop-lang.tld/headless/fileadmin', $urlUtility->getStorageProxyUrl());

        $manualRequest = $this->prophesize(ServerRequest::class);
        $manualRequest->getAttribute(Argument::is('site'))->willReturn($this->createMockSite('https://test-backend-api.tld:8000', '', []));
        $manualRequest->getAttribute(Argument::is('language'))->willReturn(new SiteLanguage(0, 'en', new Uri('/'), [
            'title' =>  'English',
            'enabled' =>  true,
            'languageId' =>  0,
            'base' => ' /',
            'typo3Language' =>  'default',
            'locale' =>  'en_US.UTF-8',
            'iso-639-1'=>  'en',
            'navigationTitle' =>  'English',
            'hreflang' => 'en-us',
            'direction' =>  'ltr',
            'flag' =>  'us',
            'frontendBase' => 'https://frontend-domain-from-lang.tld',
            'frontendApiProxy' => 'https://frontend-domain-from-lang.tld/headless/',
            'frontendFileApi' => 'https://frontend-domain-from-lang.tld/headless/fileadmin',
            'baseVariants' => [
                [
                    'base' => 'https://test-backend-api.tld',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://test-frontend-from-from-request-lang.tld/',
                    'frontendApiProxy' => 'https://test-frontend-from-from-request-lang.tld/headless/',
                    'frontendFileApi' => 'https://test-frontend-from-from-request-lang.tld/headless/fileadmin/',
                ],
            ],
        ]));

        $urlUtility = new UrlUtility(null, $resolver->reveal(), $siteFinder);
        $urlUtilityWithRequest = $urlUtility->withRequest($manualRequest->reveal());
        self::assertSame('https://test-frontend-from-from-request-lang.tld', $urlUtilityWithRequest->getFrontendUrl());
        self::assertSame('https://test-frontend-from-from-request-lang.tld/headless', $urlUtilityWithRequest->getProxyUrl());
        self::assertSame('https://test-frontend-from-from-request-lang.tld/headless/fileadmin', $urlUtilityWithRequest->getStorageProxyUrl());
    }

    protected function createMockSite(string $backendUrl, string $frontendUrl = '', ?array $variants = null)
    {
        $site = $this->prophesize(Site::class);

        if ($variants === null) {
            $variants = [
                [
                    'base' => $backendUrl,
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => $frontendUrl,
                    'frontendApiProxy' => $frontendUrl . '/headless',
                    'frontendFileApi' => $frontendUrl . '/headless/fileadmin',
                ],
            ];
        }
        $site->getConfiguration()->shouldBeCalled(2)->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => $variants,
        ]);

        $uri = new Uri($backendUrl);
        $site->getBase()->willReturn($uri);
        return $site->reveal();
    }
}
