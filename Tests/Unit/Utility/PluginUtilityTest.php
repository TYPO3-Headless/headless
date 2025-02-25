<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Utility\PluginUtility;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;

use function json_decode;

class PluginUtilityTest extends TestCase
{
    public function testProperException(): void
    {
        $urlUtility = new UrlUtility(new Features(), $this->createMock(Resolver::class), $this->createMock(SiteFinder::class));

        $pluginRedirect = new PluginUtility($urlUtility);

        $this->expectException(PropagateResponseException::class);

        $pluginRedirect->redirect(new ServerRequest(), '/test');
    }

    public function testResponse(): void
    {
        $urlUtility = new UrlUtility(new Features(), $this->createMock(Resolver::class), $this->createMock(SiteFinder::class));

        $pluginRedirect = new PluginUtility($urlUtility);

        try {
            $pluginRedirect->redirect(new ServerRequest(), '/test');
        } catch (PropagateResponseException $exception) {
            self::assertSame(['redirectUrl' => '/test', 'statusCode' => 307], json_decode($exception->getResponse()->getBody()->getContents(), true));
        }
    }
}
