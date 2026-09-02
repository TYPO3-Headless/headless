<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\PluginUtility;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;

use TYPO3\CMS\Core\Site\SiteFinder;

use TYPO3\CMS\Core\Utility\GeneralUtility;

use function json_decode;

class PluginUtilityTest extends HeadlessUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);
    }

    public function testProperException(): void
    {
        $urlUtility = new UrlUtility($this->createMock(Resolver::class), $this->createMock(SiteFinder::class), new HeadlessMode());

        $pluginRedirect = new PluginUtility($urlUtility);

        $this->expectException(PropagateResponseException::class);

        $pluginRedirect->redirect(new ServerRequest(), '/test');
    }

    public function testResponse(): void
    {
        $urlUtility = new UrlUtility($this->createMock(Resolver::class), $this->createMock(SiteFinder::class), new HeadlessMode());

        $pluginRedirect = new PluginUtility($urlUtility);

        try {
            $pluginRedirect->redirect(new ServerRequest(), '/test');
        } catch (PropagateResponseException $exception) {
            self::assertSame(['redirectUrl' => '/test', 'statusCode' => 307], json_decode($exception->getResponse()->getBody()->getContents(), true));
        }
    }
}
