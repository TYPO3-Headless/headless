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

class MenuCategorizedContentElementTest extends BaseContentTypeTest
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendRequest(
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
        self::assertEquals('17', $firstCategorizedContentElement['uid']);
        self::assertEquals('1', $firstCategorizedContentElement['pid']);
        self::assertEquals('1', $firstCategorizedContentElement['sorting']);
        self::assertEquals('header', $firstCategorizedContentElement['CType']);
        self::assertEquals('default', $firstCategorizedContentElement['frame_class']);
        self::assertEquals('1', $firstCategorizedContentElement['colPos']);
        self::assertEquals('3', $firstCategorizedContentElement['categories']);

        $secondCategorizedContentElement = $contentElement['content']['menu'][1];
        self::assertEquals('18', $secondCategorizedContentElement['uid']);
        self::assertEquals('1', $secondCategorizedContentElement['pid']);
        self::assertEquals('1', $secondCategorizedContentElement['sorting']);
        self::assertEquals('textpic', $secondCategorizedContentElement['CType']);
        self::assertEquals('default', $secondCategorizedContentElement['frame_class']);
        self::assertEquals('1', $secondCategorizedContentElement['colPos']);
        self::assertEquals('3', $secondCategorizedContentElement['categories']);
    }
}
