<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\ContentTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class BasicListElementTest extends BaseContentTypeTesting
{
    public function testBasicListContentElement()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][4];

        // Note: list_type field was removed in TYPO3 v14, so type is now empty for CType=list
        $this->checkDefaultContentFields($contentElement, 6, 1, '', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 'Page 1', '/page1?parameter=999&cHash=', '_blank');
        self::assertFalse(isset($contentElement['content']['bodytext']));
    }
}
