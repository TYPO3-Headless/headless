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
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
    use ProphecyTrait;

    /**
     * @test
     */
    public function processTest()
    {
        $testUri = new Uri('https://test.domain.tld');
        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->start(Argument::any());
        $mainSite = $this->getSite($testUri, 1);

        $this->prophesize(SiteProvider::class);
        $siteProvider = $this->prophesize(SiteProvider::class);
        $siteProvider->getSites()->willReturn([$mainSite]);
        $siteProvider = $siteProvider->reveal();

        $expectedValueOfAdditionalDataProcessor = ['test' => 1];
        $contentDataProcessor = $this->prophesize(ContentDataProcessor::class);
        $contentDataProcessor->process(Argument::any(), Argument::any(), Argument::any())->willReturn(
            $expectedValueOfAdditionalDataProcessor
        );

        $domainSchema = new DomainSchema($this->getUrlUtility($mainSite), $contentDataProcessor->reveal());
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

        self::assertEquals($expectedResult, $domainSchema->process($siteProvider, ['cObj' => $cObj->reveal()]));
        self::assertEquals(
            [$expectedValueOfAdditionalDataProcessor],
            $domainSchema->process(
                $siteProvider,
                ['cObj' => $cObj->reveal(), 'processorConfiguration' => ['dataProcessing.' => []]]
            )
        );
    }

    protected function getSite(UriInterface $domainUri, int $rootPageId, array $languages = ['de'])
    {
        if (!in_array('default', $languages, true)) {
            $languages[] = 'default';
        }

        $defaultLanguage = $this->prophesize(SiteLanguage::class);
        $defaultLanguage->getTypo3Language()->willReturn('default');

        $site = $this->prophesize(Site::class);

        $site->getBase()->willReturn($domainUri);
        $site->getDefaultLanguage()->willReturn($defaultLanguage->reveal());
        $site->getRootPageId()->willReturn($rootPageId);

        $siteLanguages = [];
        foreach ($languages as $language) {
            $siteLanguage = $this->prophesize(SiteLanguage::class);

            $siteLanguage->getTypo3Language()
                ->willReturn($language);

            $siteLanguages[] = $siteLanguage->reveal();
        }

        $site->getConfiguration()->willReturn([
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

        $site
            ->getLanguages()
            ->willReturn($siteLanguages);
        return $site->reveal();
    }

    protected function getUrlUtility($site = null): UrlUtility
    {
        $uri = new Uri('https://test-backend-api.tld');

        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $siteFinder = $this->prophesize(SiteFinder::class);

        if ($site === null) {
            $site = $this->getSiteWithBase($uri);
        }

        $siteFinder->getSiteByPageId(Argument::is(1))->willReturn($site);
        $dummyRequest = (new ServerRequest())->withAttribute('site', $site);

        return new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal(), $dummyRequest);
    }

    protected function getSiteWithBase(UriInterface $uri, $withLanguage = null)
    {
        $site = $this->prophesize(Site::class);
        $site->getConfiguration()->willReturn([
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

        $site->getBase()->willReturn($uri);

        if ($withLanguage === null) {
            $withLanguage = $this->prophesize(SiteLanguage::class);
            $withLanguage->reveal();
        }

        return $site->reveal();
    }
}
