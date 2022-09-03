<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class ShortcutElementTest extends BaseContentTypeTest
{
    public function testShortcutContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][7];

        $this->checkDefaultContentFields($contentElement, 9, 1, 'shortcut', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        self::assertFalse(isset($contentElement['content']['header']));
        self::assertFalse(isset($contentElement['content']['bodytext']));
        self::assertTrue(isset($contentElement['content']['shortcut']));
        self::assertCount(2, $contentElement['content']['shortcut']);

        // element at pos 0 is our TextMediaElement
        $this->checkDefaultContentFields($contentElement['content']['shortcut'][0], 2, 1, 'textmedia', 1);
        $this->checkAppearanceFields($contentElement['content']['shortcut'][0]);
        $this->checkHeaderFields($contentElement['content']['shortcut'][0]);
        self::assertFalse(isset($contentElement['content']['shortcut'][0]['headerLink']));
        self::assertFalse(isset($contentElement['content']['shortcut'][0]['bodytext']));

        // element at pos 1 is our TextElement
        $this->checkDefaultContentFields($contentElement['content']['shortcut'][1], 1, 1, 'text', 0, 'SysCategory1Title,SysCategory2Title');
        $this->checkAppearanceFields($contentElement['content']['shortcut'][1], 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement['content']['shortcut'][1], 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement['content']['shortcut'][1], 't3://page?uid=2 _blank LinkClass LinkTitle parameter=999', '/page1?parameter=999&amp;cHash=', '_blank');
        self::assertStringContainsString('<a href="/page1?parameter=999&amp;cHash=', $contentElement['content']['shortcut'][1]['content']['bodytext']);
    }
}
