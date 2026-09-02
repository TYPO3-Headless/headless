<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\HeadlessHreflangGeneratorListener;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;

use function str_replace;

class HeadlessHreflangGeneratorListenerTest extends HeadlessUnitTestCase
{
    public function testDoesNothingWhenHeadlessModeIsDisabled(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $event = new ModifyHrefLangTagsEvent(new ServerRequest());
        $event->setHrefLangs(['en-US' => 'https://api.example.tld/']);

        (new HeadlessHreflangGeneratorListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame(['en-US' => 'https://api.example.tld/'], $event->getHrefLangs());
    }

    public function testDoesNothingForEmptyHrefLangs(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');

        $event = new ModifyHrefLangTagsEvent($this->createFullModeRequest());

        (new HeadlessHreflangGeneratorListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame([], $event->getHrefLangs());
    }

    public function testRewritesHrefLangsToFrontendUrls(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrlWithSite')->willReturnCallback(
            static fn(string $url): string => str_replace('api.example.tld', 'front.example.tld', $url)
        );

        $event = new ModifyHrefLangTagsEvent($this->createFullModeRequest());
        $event->setHrefLangs([
            'en-US' => 'https://api.example.tld/',
            'pl-PL' => 'https://api.example.tld/pl',
        ]);

        (new HeadlessHreflangGeneratorListener($urlUtility, new HeadlessMode()))($event);

        self::assertSame(
            [
                'en-US' => 'https://front.example.tld/',
                'pl-PL' => 'https://front.example.tld/pl',
            ],
            $event->getHrefLangs()
        );
    }

    private function createFullModeRequest(): ServerRequest
    {
        return (new ServerRequest())
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL))
            ->withAttribute('site', $this->createMock(Site::class));
    }
}
