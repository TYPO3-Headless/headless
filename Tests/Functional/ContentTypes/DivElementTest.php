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

class DivElementTest extends BaseContentTypeTest
{
    public function testDivContentElement()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][3];

        $this->checkDefaultContentFields($contentElement, 5, 1, 'div', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        self::assertEquals('Header', $contentElement['content']['header']);
        self::assertFalse(isset($contentElement['content']['subheader']));
        self::assertFalse(isset($contentElement['content']['bodytext']));
    }
}
