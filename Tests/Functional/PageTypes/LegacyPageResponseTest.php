<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\PageTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class LegacyPageResponseTest extends BasePageTypesHeadlessTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/Sets/Headless/setup.typoscript',
        'EXT:headless/Configuration/Sets/HeadlessLegacy/setup.typoscript',
    ];

    public function testLegacyPageResponseStructure(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $page = json_decode((string)$response->getBody(), true);

        self::assertEquals(1, $page['id'], 'id mismatch');
        self::assertEquals('Standard', $page['type'], 'type mismatch');
        self::assertEquals('/', $page['slug'], 'slug mismatch');

        foreach (['media', 'seo', 'meta', 'categories', 'breadcrumbs', 'appearance', 'content', 'i18n'] as $key) {
            self::assertArrayHasKey($key, $page, sprintf('"%s" key missing in legacy page response', $key));
        }

        self::assertIsArray($page['media'], 'media not an array');
    }
}
