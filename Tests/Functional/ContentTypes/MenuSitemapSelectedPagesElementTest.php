<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class MenuSitemapSelectedPagesTest extends BaseContentTypeTest
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/page3')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][0];

        $this->checkDefaultContentFields($contentElement, 21, 5, 'menu_sitemap_pages', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        self::assertIsArray($contentElement['appearance']);
        self::assertIsArray($contentElement['content']);
        self::assertIsArray($contentElement['content']['menu']);
        self::assertIsArray($contentElement['content']['menu'][0]);

        self::assertEquals('Page 4', $contentElement['content']['menu'][0]['title']);
        self::assertEquals('/page4', $contentElement['content']['menu'][0]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['spacer']);
        self::assertArrayHasKey('children', $contentElement['content']['menu'][0]);
        self::assertIsArray($contentElement['content']['menu'][0]['children']);
        self::assertIsArray($contentElement['content']['menu'][0]['images']);
        self::assertEmpty($contentElement['content']['menu'][0]['images']);
        self::assertEquals('Page 5', $contentElement['content']['menu'][0]['children'][0]['title']);
        self::assertEquals('/page5', $contentElement['content']['menu'][0]['children'][0]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['children'][0]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['children'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['children'][0]['spacer']);
        self::assertEmpty($contentElement['content']['menu'][0]['children'][0]['images']);
    }
}
