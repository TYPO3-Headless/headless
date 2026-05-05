<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\DomainSchema;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DomainSchemaTest extends UnitTestCase
{
    public function testProcess(): void
    {
        $testUri = new Uri('https://test.domain.tld');
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $mainSite = $this->getSite($testUri, 1);

        $siteProvider = $this->createMock(SiteProvider::class);
        $siteProvider->method('getSites')->willReturn([$mainSite]);

        $expectedValueOfAdditionalDataProcessor = ['test' => 1];
        $contentDataProcessor = $this->createMock(ContentDataProcessor::class);
        $contentDataProcessor->method('process')->willReturn($expectedValueOfAdditionalDataProcessor);

        $domainSchema = new DomainSchema($this->getUrlUtility($mainSite), $contentDataProcessor);
        $expectedResult = [
            [
                'name' => $testUri->getHost(),
                'baseURL' => (string)$testUri,
                'api' =>
                    [
                        'baseURL' => $testUri . '/headless',
                    ],
                'i18n' =>
                    [
                        'locales' => ['de', 'default'],
                        'defaultLocale' => 'default',
                    ],
            ],
        ];

        self::assertEquals($expectedResult, $domainSchema->process($siteProvider, ['cObj' => $cObj]));
        self::assertEquals(
            [$expectedValueOfAdditionalDataProcessor],
            $domainSchema->process(
                $siteProvider,
                ['cObj' => $cObj, 'processorConfiguration' => ['dataProcessing.' => []]]
            )
        );
    }

    protected function getSite(UriInterface $domainUri, int $rootPageId, array $languages = ['de'])
    {
        if (!in_array('default', $languages, true)) {
            $languages[] = 'default';
        }

        $defaultLanguage = $this->createMock(SiteLanguage::class);
        $defaultLanguage->method('getTypo3Language')->willReturn('default');

        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn($domainUri);
        $site->method('getDefaultLanguage')->willReturn($defaultLanguage);
        $site->method('getRootPageId')->willReturn($rootPageId);

        $siteLanguages = [];
        foreach ($languages as $language) {
            $siteLanguage = $this->createMock(SiteLanguage::class);
            $siteLanguage->method('toArray')->willReturn([]);
            $siteLanguage->method('getTypo3Language')->willReturn($language);
            $siteLanguages[] = $siteLanguage;
        }

        $site->method('getConfiguration')->willReturn([
            'base' => 'https://www.typo3.org',
            'languages' => [],
            'baseVariants' => [
                [
                    'base' => (string)$domainUri,
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => $domainUri . ':3000',
                    'frontendApiProxy' => $domainUri . '/headless',
                    'frontendFileApi' => $domainUri . '/headless/fileadmin',
                ],
            ],
        ]);

        $site->method('getLanguages')->willReturn($siteLanguages);
        return $site;
    }

    protected function getUrlUtility($site = null): UrlUtility
    {
        $uri = new Uri('https://test-backend-api.tld');

        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturn(true);

        $mock = $this->createPartialMock(SiteFinder::class, ['getSiteByPageId']);
        $mock->method('getSiteByPageId')->willReturn($site);

        if ($site === null) {
            $site = $this->getSiteWithBase($uri);
        }

        $mock->method('getSiteByPageId')->willReturn($site);

        $dummyRequest = (new ServerRequest())->withAttribute('site', $site);
        $dummyRequest = $dummyRequest->withAttribute('headless', new Headless());

        return new UrlUtility(null, $resolver, $mock, $dummyRequest, (new HeadlessMode())->withRequest($dummyRequest));
    }

    protected function getSiteWithBase(UriInterface $uri, $withLanguage = null)
    {
        $site = $this->createMock(Site::class);
        $site->method('getConfiguration')->willReturn([
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
        ]);

        $site->method('getBase')->willReturn($uri);

        return $site;
    }
}
