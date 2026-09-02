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

class MenuSectionPagesElementTest extends BaseContentTypeTesting
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1']['13'];

        $this->checkDefaultContentFields($contentElement, 32, 1, 'menu_section_pages', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        $menu = $contentElement['content']['menu'];

        self::assertIsArray($menu);
        self::assertCount(5, $menu);

        self::assertEquals('Page 1', $menu[0]['title']);
        self::assertEquals('/page1', $menu[0]['link']);
        self::assertEquals('0', $menu[0]['spacer']);
        self::assertIsArray($menu[0]['media']);
        self::assertIsArray($menu[0]['content']);
        self::assertEquals(20, $menu[0]['content'][0]['uid']);
        self::assertEquals('Header', $menu[0]['content'][0]['header']);

        self::assertEquals('Page 3', $menu[2]['title']);
        self::assertEquals('/page3', $menu[2]['link']);
        self::assertIsArray($menu[2]['content']);
        self::assertEquals(21, $menu[2]['content'][0]['uid']);
    }
}
