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

class StructurePageTypesTest extends BasePageTypesHeadlessTesting
{
    public function testGetMenuStructure()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/?type=834')
        );

        self::assertEquals(200, $response->getStatusCode());

        $pageTree = json_decode((string)$response->getBody(), true);
        self::assertTrue(isset($pageTree['navigation']));
        self::assertCount(1, $pageTree['navigation']);
        self::assertTrue(isset($pageTree['navigation'][0]));
        self::assertCount(5, $pageTree['navigation'][0]['children']);
        self::assertCount(1, $pageTree['navigation'][0]['children'][0]['children']);
        self::assertCount(1, $pageTree['navigation'][0]['children'][2]['children']);
        self::assertCount(1, $pageTree['navigation'][0]['children'][4]['children']);

        self::assertEquals('/', $pageTree['navigation'][0]['link']);
        self::assertEquals('/page1', $pageTree['navigation'][0]['children'][0]['link']);
        self::assertEquals('/page1/page1_1', $pageTree['navigation'][0]['children'][0]['children'][0]['link']);
        self::assertEquals('/page2', $pageTree['navigation'][0]['children'][1]['link']);
        self::assertEquals('/page3', $pageTree['navigation'][0]['children'][2]['link']);
        self::assertEquals('/page4', $pageTree['navigation'][0]['children'][2]['children'][0]['link']);
        self::assertEquals('/page5', $pageTree['navigation'][0]['children'][2]['children'][0]['children'][0]['link']);
        self::assertEquals('/page8', $pageTree['navigation'][0]['children'][3]['link']);
        self::assertEquals('/page9', $pageTree['navigation'][0]['children'][4]['link']);
        self::assertEquals('/page10', $pageTree['navigation'][0]['children'][4]['children'][0]['link']);
    }
}
