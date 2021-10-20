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

class MenuSectionElementTest extends BaseContentTypeTest
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][6];

        self::assertIsArray($contentElement['appearance']);
        self::assertIsArray($contentElement['content']);
        self::assertIsArray($contentElement['content']['menu']);
        self::assertIsArray($contentElement['content']['menu'][0]);
        self::assertIsArray($contentElement['content']['menu'][1]);

        self::assertEquals('Root', $contentElement['content']['menu'][0]['title']);
        self::assertEquals('/', $contentElement['content']['menu'][0]['link']);
        self::assertEquals('1', $contentElement['content']['menu'][0]['active']);
        self::assertEquals('1', $contentElement['content']['menu'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['spacer']);
        self::assertIsArray($contentElement['content']['menu'][0]['media']);
        self::assertIsArray($contentElement['content']['menu'][0]['content']);

        self::assertEquals('Page 2', $contentElement['content']['menu'][1]['title']);
        self::assertEquals('/page2', $contentElement['content']['menu'][1]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][1]['spacer']);
        self::assertIsArray($contentElement['content']['menu'][1]['media']);
        self::assertIsArray($contentElement['content']['menu'][1]['content']);
    }
}
