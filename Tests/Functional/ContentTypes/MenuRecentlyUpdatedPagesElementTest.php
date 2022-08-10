<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class MenuRecentlyUpdatedPagesElementTest extends BaseContentTypeTest
{
    public function setUp(): void
    {
        parent::setUp();
        $currentDate = new DateTime();
        $modifiedOneDayAgo = (clone $currentDate)->modify('-1 day');
        $modifiedSevenDayAgo = (clone $currentDate)->modify('-7 day');
        $modifiedEightDayAgo = (clone $currentDate)->modify('-8 day');
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $connection->prepare('update pages set SYS_LASTCHANGED = ? WHERE uid = 2')->execute([$modifiedOneDayAgo->getTimestamp()]);
        $connection->prepare('update pages set SYS_LASTCHANGED = ? WHERE uid = 3')->execute([$modifiedSevenDayAgo->getTimestamp()]);
        $connection->prepare('update pages set SYS_LASTCHANGED = ? WHERE uid = 4')->execute([$modifiedEightDayAgo->getTimestamp()]);
    }

    public function testMenuContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][7];

        $this->checkDefaultContentFields($contentElement, 16, 1, 'menu_recently_updated', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        self::assertTrue(is_array($contentElement['appearance']));
        self::assertTrue(is_array($contentElement['content']));
        self::assertTrue(is_array($contentElement['content']['menu']));
        self::assertTrue(is_array($contentElement['content']['menu'][0]));
        self::assertTrue(is_array($contentElement['content']['menu'][1]));

        self::assertEquals('Page 1', $contentElement['content']['menu'][0]['title']);
        self::assertEquals('/page1', $contentElement['content']['menu'][0]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['spacer']);
        self::assertTrue(is_array($contentElement['content']['menu'][0]['media']));

        self::assertEquals('Page 1.1', $contentElement['content']['menu'][1]['title']);
        self::assertEquals('/page1/page1_1', $contentElement['content']['menu'][1]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['spacer']);
        self::assertTrue(is_array($contentElement['content']['menu'][1]['media']));

        self::assertArrayNotHasKey(2, $contentElement['content']['menu']);
    }
}
