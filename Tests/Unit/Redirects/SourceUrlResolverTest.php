<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Redirects;

use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class SourceUrlResolverTest extends HeadlessUnitTestCase
{
    public function testReturnsNullForUnknownSourceHost(): void
    {
        $site = $this->createSite('api.example.tld', ['headless' => 1, 'frontendBase' => 'https://front.example.tld']);

        self::assertNull(
            $this->createResolver([$site])->resolve('api.unknown.tld', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testReturnsNullWhenHeadlessModeIsOff(): void
    {
        $site = $this->createSite('api.example.tld', ['frontendBase' => 'https://front.example.tld']);

        self::assertNull(
            $this->createResolver([$site])->resolve('api.example.tld', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testReturnsNullWhenHeadlessModeIsMixed(): void
    {
        $site = $this->createSite('api.example.tld', ['headless' => 2, 'frontendBase' => 'https://front.example.tld']);

        self::assertNull(
            $this->createResolver([$site])->resolve('api.example.tld', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testRewritesSourceUrlToFrontendBaseInFullMode(): void
    {
        $site = $this->createSite('api.example.tld', ['headless' => 1, 'frontendBase' => 'https://front.example.tld']);

        self::assertSame(
            'https://front.example.tld/Ab12Cd34',
            $this->createResolver([$site])->resolve('api.example.tld', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testMatchesSiteByLanguageBaseHostAndUsesLanguageFrontendBase(): void
    {
        $language = $this->createMock(SiteLanguage::class);
        $language->method('getBase')->willReturn(new Uri('https://api-en.example.tld/'));
        $language->method('toArray')->willReturn(['frontendBase' => 'https://front-en.example.tld']);

        $site = $this->createSite(
            'api.example.tld',
            ['headless' => 1, 'frontendBase' => 'https://front.example.tld'],
            [$language]
        );

        self::assertSame(
            'https://front-en.example.tld/Ab12Cd34',
            $this->createResolver([$site])->resolve('api-en.example.tld', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testWildcardHostResolvesAgainstSoleFullHeadlessSite(): void
    {
        $site = $this->createSite('api.example.tld', ['headless' => 1, 'frontendBase' => 'https://front.example.tld']);
        $nonHeadless = $this->createSite('other.example.tld', []);

        self::assertSame(
            'https://front.example.tld/Ab12Cd34',
            $this->createResolver([$nonHeadless, $site])->resolve('*', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testWildcardHostStaysUnresolvedWithSeveralFullHeadlessSites(): void
    {
        $siteA = $this->createSite('api-a.example.tld', ['headless' => 1, 'frontendBase' => 'https://front-a.example.tld']);
        $siteB = $this->createSite('api-b.example.tld', ['headless' => 1, 'frontendBase' => 'https://front-b.example.tld']);

        self::assertNull(
            $this->createResolver([$siteA, $siteB])->resolve('*', '/Ab12Cd34', new ServerRequest())
        );
    }

    public function testWildcardHostStaysUnresolvedWithoutFullHeadlessSite(): void
    {
        $mixed = $this->createSite('api.example.tld', ['headless' => 2, 'frontendBase' => 'https://front.example.tld']);

        self::assertNull(
            $this->createResolver([$mixed])->resolve('*', '/Ab12Cd34', new ServerRequest())
        );
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<SiteLanguage> $languages
     */
    private function createSite(string $host, array $configuration, array $languages = []): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn(new Uri('https://' . $host . '/'));
        $site->method('getConfiguration')->willReturn($configuration);
        $site->method('getLanguages')->willReturn($languages);

        return $site;
    }

    /**
     * @param list<Site> $sites
     */
    private function createResolver(array $sites): SourceUrlResolver
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn($sites);

        return new SourceUrlResolver(
            $siteFinder,
            new HeadlessMode(),
            new UrlUtility($this->createMock(Resolver::class), $siteFinder, new HeadlessMode())
        );
    }
}
