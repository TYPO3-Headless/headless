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

class BulletsElementTest extends BaseContentTypeTest
{
    public function testBulletsContentElement()
    {
        $testBulletsContent = ['Top1', 'Top2', 'Top3'];

        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][6];

        $this->checkDefaultContentFields($contentElement, 8, 1, 'bullets', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 'Page 1', '/page1?parameter=999&cHash=', '_blank');

        self::assertEquals(1, $contentElement['content']['bulletsType']);
        self::assertTrue(is_array($contentElement['content']['bodytext']));
        self::assertEquals($testBulletsContent, $contentElement['content']['bodytext']);
    }
}
