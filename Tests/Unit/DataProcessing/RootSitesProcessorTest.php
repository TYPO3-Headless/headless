<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSitesProcessor;
use FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing\TestDomainSchema;
use FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing\TestSiteProvider;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RootSitesProcessorTest extends UnitTestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;

        parent::setUp();
    }

    /**
     * @test
     */
    public function customImplementation(): void
    {
        $processor = new RootSitesProcessor();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());
        $contentObjectRenderer->data['uid'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        $conf = [];
        $conf['siteProvider'] = TestSiteProvider::class;
        $conf['siteSchema'] = TestDomainSchema::class;

        self::assertEquals([
            'sites' => [
                [
                    'name' => 'frontend.tld',
                    'baseURL' => 'https://frontend.tld',
                    'api' => ['baseURL' => '/proxy/'],
                    'i18n' => [
                        'locales' => ['default'],
                        'defaultLocale' => 'default'
                    ],
                ],
                [
                    'name' => 'frontend.tld',
                    'baseURL' => 'https://frontend.tld',
                    'api' => ['baseURL' => '/proxy/'],
                    'i18n' => [
                        'locales' => ['default'],
                        'defaultLocale' => 'default'
                    ],
                ]
            ]
        ], $processor->process($contentObjectRenderer, [], $conf, []));
    }

    /**
     * @test
     */
    public function objectNotSet()
    {
        $processor = new RootSitesProcessor();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());

        $conf = [];
        self::assertEquals([], $processor->process($contentObjectRenderer, [], $conf, []));
    }

    /**
     * @test
     */
    public function objectSetButFeatureDisabled(): void
    {
        $processor = new RootSitesProcessor();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());
        $contentObjectRenderer->data['uid'] = 1;
        $this->expectException(\RuntimeException::class);
        self::assertEquals([], $processor->process($contentObjectRenderer, [], [], []));
    }

    /**
     * @test
     */
    public function featureEnabledButWrongSiteProvider(): void
    {
        $processor = new RootSitesProcessor();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());
        $contentObjectRenderer->data['uid'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        $conf = [];
        $conf['siteProvider'] = \stdClass::class;
        $this->expectException(\InvalidArgumentException::class);
        self::assertEquals([], $processor->process($contentObjectRenderer, [], $conf, []));
    }

    /**
     * @test
     */
    public function featureEnabledButWrongSiteSchema(): void
    {
        $processor = new RootSitesProcessor();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());
        $contentObjectRenderer->data['uid'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
        $conf = [];
        $conf['siteSchema'] = \stdClass::class;
        $this->expectException(\InvalidArgumentException::class);
        self::assertEquals([], $processor->process($contentObjectRenderer, [], $conf, []));
    }
}
