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

class MenuSitemapMediaTest extends BaseContentTypeTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/Sets/Headless/setup.typoscript',
    ];

    public function testMenuSitemapItemsExposeMedia(): void
    {
        $menu = $this->fetchMenu('https://website.local/', 4, 'menu_sitemap');

        self::assertIsArray($menu[0]['media']);
        self::assertArrayNotHasKey('images', $menu[0]);
        self::assertIsArray($menu[0]['children'][0]['media']);
        self::assertArrayNotHasKey('images', $menu[0]['children'][0]);
    }

    public function testMenuSitemapPagesItemsExposeMedia(): void
    {
        $menu = $this->fetchMenu('https://website.local/page3', 0, 'menu_sitemap_pages');

        self::assertIsArray($menu[0]['media']);
        self::assertArrayNotHasKey('images', $menu[0]);
        self::assertIsArray($menu[0]['children'][0]['media']);
        self::assertArrayNotHasKey('images', $menu[0]['children'][0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchMenu(string $url, int $index, string $type): array
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest($url));

        self::assertSame(200, $response->getStatusCode());

        $contentElement = json_decode((string)$response->getBody(), true)['content']['colPos1'][$index];

        self::assertSame($type, $contentElement['type']);

        return $contentElement['content']['menu'];
    }
}
