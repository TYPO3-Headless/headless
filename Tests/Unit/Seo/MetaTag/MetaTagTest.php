<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Seo\MetaTag;

use FriendsOfTYPO3\Headless\Seo\MetaTag\Html5MetaTagManager;
use FriendsOfTYPO3\Headless\Seo\MetaTag\OpenGraphMetaTagManager;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class MetaTagTest extends UnitTestCase
{
    use ProphecyTrait;
    protected bool $resetSingletonInstances = true;

    public function testProps(): void
    {
        $container = new Container();
        $pageRenderer = $this->prophesize(PageRenderer::class);
        $pageRenderer->getDocType()->willReturn(\TYPO3\CMS\Core\Type\DocType::html5);

        $container->set(PageRenderer::class, $pageRenderer->reveal());

        GeneralUtility::setContainer($container);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest());

        $registry = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);

        $registry->registerManager(
            'html5',
            Html5MetaTagManager::class
        );

        $registry->registerManager(
            'opengraph',
            OpenGraphMetaTagManager::class
        );

        $htmlManager = $registry->getManagerForProperty('generator');
        $htmlManager->addProperty('generator', 'TYPO3 CMS x T3Headless', [], true, 'name');
        $htmlManager->addProperty('content-language', 'pl-PL', [], true, 'name');

        $ogManager = $registry->getManagerForProperty('og:image');
        $ogManager->addProperty('og:image', 'Powered by TYPO3', ['url' => 'https://example.com/image.jpg'], true, 'name');

        self::assertSame('<meta name="generator" content="TYPO3 CMS x T3Headless">
<meta http-equiv="content-language" content="pl-PL">', $htmlManager->renderAllProperties());

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL));

        self::assertSame('[{"http-equiv":"content-language","content":"pl-PL"}]', $htmlManager->renderProperty('content-language'));
        self::assertSame('[{"name":"generator","content":"TYPO3 CMS x T3Headless"}]', $htmlManager->renderProperty('generator'));
        self::assertSame('[{"name":"generator","content":"TYPO3 CMS x T3Headless"},{"http-equiv":"content-language","content":"pl-PL"}]', $htmlManager->renderAllProperties());
        self::assertSame('[{"property":"og:image","content":"Powered by TYPO3"},{"property":"og:image:url","content":"https:\/\/example.com\/image.jpg"}]', $ogManager->renderAllProperties());
    }
}
