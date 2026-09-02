<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterPageUriGeneratedListener;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Event\AfterPageUriGeneratedEvent;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class AfterPageUriGeneratedListenerTest extends HeadlessUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testDoesNothingWithoutGlobalRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $event = $this->createEvent('https://api.example.tld/page');
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame('https://api.example.tld/page', (string)$event->getUri());
    }

    public function testDoesNothingForFrontendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $event = $this->createEvent('https://api.example.tld/page');
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame('https://api.example.tld/page', (string)$event->getUri());
    }

    public function testDoesNothingWhenSiteIsNotHeadless(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $event = $this->createEvent('https://api.example.tld/page', $this->createSite([]));
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame('https://api.example.tld/page', (string)$event->getUri());
    }

    public function testRewritesUriForFullHeadlessSite(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturn('https://front.example.tld/page');

        $event = $this->createEvent('https://api.example.tld/page', $this->createSite(['headless' => 1]));
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame('https://front.example.tld/page', (string)$event->getUri());
    }

    public function testKeepsUriInstanceWhenRewriteReturnsSameUrl(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturn('https://api.example.tld/page');

        $event = $this->createEvent('https://api.example.tld/page', $this->createSite(['headless' => 1]));
        $originalUri = $event->getUri();
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame($originalUri, $event->getUri());
    }

    public function testKeepsUriWhenRewrittenUriIsInvalid(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturn('http:///invalid');

        $event = $this->createEvent('https://api.example.tld/page', $this->createSite(['headless' => 1]));
        (new AfterPageUriGeneratedListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame('https://api.example.tld/page', (string)$event->getUri());
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSite(array $configuration): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getConfiguration')->willReturn($configuration);

        return $site;
    }

    private function createEvent(string $uri, ?Site $site = null): AfterPageUriGeneratedEvent
    {
        return new AfterPageUriGeneratedEvent(
            new Uri($uri),
            1,
            [],
            '',
            'page',
            $this->createMock(SiteLanguage::class),
            $site ?? $this->createMock(Site::class)
        );
    }
}
