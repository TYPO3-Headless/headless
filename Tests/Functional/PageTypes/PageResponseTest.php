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

class PageResponseTest extends BasePageTypesHeadlessTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/Sets/Headless/setup.typoscript',
    ];

    public function testDefaultPageResponseStructure(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $page = json_decode((string)$response->getBody(), true);

        self::assertEquals(1, $page['id'], 'id mismatch');
        self::assertEquals('Standard', $page['type'], 'type mismatch');
        self::assertEquals('/', $page['slug'], 'slug mismatch');

        foreach (['media', 'seo', 'breadcrumbs', 'appearance', 'content', 'i18n'] as $key) {
            self::assertArrayHasKey($key, $page, sprintf('"%s" key missing in default page response', $key));
        }

        self::assertIsArray($page['media'], 'media not an array');
    }
}
