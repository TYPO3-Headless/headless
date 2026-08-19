<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Seo\MetaTag;

use FriendsOfTYPO3\Headless\Seo\MetaTag\AbstractMetaTagManager;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractMetaTagManagerTest extends HeadlessUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testCustomContentAttributeFromHandledPropertiesIsUsed(): void
    {
        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);

        $manager = new class () extends AbstractMetaTagManager {
            protected $handledProperties = [
                'special' => ['contentAttribute' => 'data-content'],
            ];
        };
        $manager->addProperty('special', 'value', [], true);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        self::assertSame(
            '[{"name":"special","data-content":"value"}]',
            $manager->renderProperty('special')
        );
    }
}
