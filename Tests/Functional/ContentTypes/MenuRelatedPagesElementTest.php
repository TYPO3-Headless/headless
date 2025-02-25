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

class MenuRelatedPagesElementTest extends BaseContentTypeTesting
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][11];

        $this->checkDefaultContentFields($contentElement, 22, 1, 'menu_related_pages', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        self::assertIsArray($contentElement['appearance']);
        self::assertIsArray($contentElement['content']);
        self::assertIsArray($contentElement['content']['menu']);
        self::assertIsArray($contentElement['content']['menu'][0]);
        self::assertIsArray($contentElement['content']['menu'][1]);

        self::assertEquals('Page 10', $contentElement['content']['menu'][0]['title']);
        self::assertEquals('/page10', $contentElement['content']['menu'][0]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['spacer']);
        self::assertArrayNotHasKey('children', $contentElement['content']['menu'][0]);
        self::assertIsArray($contentElement['content']['menu'][0]['media']);
        self::assertEmpty($contentElement['content']['menu'][0]['media']);

        self::assertEquals('Page 8', $contentElement['content']['menu'][1]['title']);
        self::assertEquals('/page8', $contentElement['content']['menu'][1]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['spacer']);
        self::assertArrayNotHasKey('children', $contentElement['content']['menu'][1]);
        self::assertIsArray($contentElement['content']['menu'][1]['media']);
        self::assertEmpty($contentElement['content']['menu'][1]['media']);

        self::assertEquals('Page 9', $contentElement['content']['menu'][2]['title']);
        self::assertEquals('/page9', $contentElement['content']['menu'][2]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][2]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][2]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][2]['spacer']);
        self::assertArrayNotHasKey('children', $contentElement['content']['menu'][2]);
        self::assertIsArray($contentElement['content']['menu'][2]['media']);
        self::assertEmpty($contentElement['content']['menu'][2]['media']);
    }
}
