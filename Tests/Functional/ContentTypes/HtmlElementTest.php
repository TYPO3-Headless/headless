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

class HtmlElementTest extends BaseContentTypeTest
{
    public function testHtmlContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][2];

        $this->checkDefaultContentFields($contentElement, 4, 1, 'html', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        self::assertFalse(isset($contentElement['content']['subheader']));

        // typolink parser was NOT called on bodytext
        self::assertEquals('<a href="t3://page?uid=2 _blank LinkClass LinkTitle parameter=999">Link</a>', $contentElement['content']['bodytext']);
    }
}
