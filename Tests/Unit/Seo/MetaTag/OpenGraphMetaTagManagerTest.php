<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Seo\MetaTag;

use FriendsOfTYPO3\Headless\Seo\MetaTag\OpenGraphMetaTagManager;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenGraphMetaTagManagerTest extends HeadlessUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testRenderHeadlessPropertyAsArray(): void
    {
        $manager = new OpenGraphMetaTagManager();
        $manager->addProperty('og:title', 'My title');

        self::assertSame(
            [['property' => 'og:title', 'content' => 'My title']],
            $manager->renderHeadlessPropertyAsArray('og:title')
        );
    }

    public function testSubPropertiesAreRenderedAsSeparateTags(): void
    {
        $manager = new OpenGraphMetaTagManager();
        $manager->addProperty('og:image', 'https://example.tld/img.jpg', ['width' => 400, 'height' => 300]);

        self::assertSame(
            [
                ['property' => 'og:image', 'content' => 'https://example.tld/img.jpg'],
                ['property' => 'og:image:width', 'content' => '400'],
                ['property' => 'og:image:height', 'content' => '300'],
            ],
            $manager->renderHeadlessPropertyAsArray('og:image')
        );
    }

    public function testRenderAllHeadlessPropertiesMergesAllRegisteredProperties(): void
    {
        $manager = new OpenGraphMetaTagManager();
        $manager->addProperty('og:title', 'My title');
        $manager->addProperty('og:description', 'My description');

        self::assertSame(
            [
                ['property' => 'og:title', 'content' => 'My title'],
                ['property' => 'og:description', 'content' => 'My description'],
            ],
            $manager->renderAllHeadlessPropertiesAsArray()
        );

        self::assertSame(
            '[{"property":"og:title","content":"My title"},{"property":"og:description","content":"My description"}]',
            $manager->renderAllHeadlessProperties()
        );
    }

    public function testContentIsEscaped(): void
    {
        $manager = new OpenGraphMetaTagManager();
        $manager->addProperty('og:title', 'Tom & "Jerry"');

        self::assertSame(
            [['property' => 'og:title', 'content' => 'Tom &amp; &quot;Jerry&quot;']],
            $manager->renderHeadlessPropertyAsArray('og:title')
        );
    }

    public function testRenderPropertyReturnsJsonInHeadlessModeAndHtmlOtherwise(): void
    {
        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);

        $manager = new OpenGraphMetaTagManager();
        $manager->addProperty('og:title', 'My title');

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        self::assertSame(
            '[{"property":"og:title","content":"My title"}]',
            $manager->renderProperty('og:title')
        );

        unset($GLOBALS['TYPO3_REQUEST']);

        $html = $manager->renderProperty('og:title');
        self::assertStringContainsString('<meta property="og:title"', $html);
        self::assertStringContainsString('content="My title"', $html);
    }
}
