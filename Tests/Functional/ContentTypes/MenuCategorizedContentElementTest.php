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

class MenuCategorizedContentElementTest extends BaseContentTypeTesting
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1']['10'];

        $this->checkDefaultContentFields($contentElement, 19, 1, 'menu_categorized_content', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        self::assertIsArray($contentElement['appearance']);
        self::assertIsArray($contentElement['content']);
        self::assertIsArray($contentElement['content']['menu']);
        self::assertIsArray($contentElement['content']['menu'][0]);
        self::assertIsArray($contentElement['content']['menu'][1]);

        $firstCategorizedContentElement = $contentElement['content']['menu'][0];
        self::assertSame(17, $firstCategorizedContentElement['uid']);
        self::assertArrayHasKey('header', $firstCategorizedContentElement);
        self::assertArrayHasKey('media', $firstCategorizedContentElement);

        $secondCategorizedContentElement = $contentElement['content']['menu'][1];
        self::assertSame(18, $secondCategorizedContentElement['uid']);
        self::assertArrayHasKey('header', $secondCategorizedContentElement);
        self::assertArrayHasKey('media', $secondCategorizedContentElement);
    }
}
