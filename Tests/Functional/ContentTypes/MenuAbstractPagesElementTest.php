<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class MenuAbstractPagesElementTest extends BaseContentTypeTest
{
    public function testMenuContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1']['12'];

        $this->checkDefaultContentFields($contentElement, 23, 1, 'menu_abstract', 1);
        $this->checkAppearanceFields($contentElement, 'default', 'default', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 0, 2);

        self::assertTrue(is_array($contentElement['appearance']));
        self::assertTrue(is_array($contentElement['content']));
        self::assertTrue(is_array($contentElement['content']['menu']));
        self::assertTrue(is_array($contentElement['content']['menu'][0]));
        self::assertTrue(is_array($contentElement['content']['menu'][0]['media']));

        self::assertEquals('Page 4', $contentElement['content']['menu'][0]['title']);
        self::assertEquals('/page4', $contentElement['content']['menu'][0]['link']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['active']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['current']);
        self::assertEquals('0', $contentElement['content']['menu'][0]['spacer']);
        self::assertEquals('Test', $contentElement['content']['menu'][0]['abstract']);
    }
}
