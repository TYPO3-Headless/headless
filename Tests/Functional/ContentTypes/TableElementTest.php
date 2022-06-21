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

class TableElementTest extends BaseContentTypeTest
{
    public function testTableContentElement()
    {
        $testTableContent = json_decode('[["Cell1.1","Cell1.2","Cell1.3","Cell1.4","",""],["Cell2.1","","","","",""],["Cell3.1","","","","",""],["\"","","","","",""]]', true);

        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][5];

        $this->checkDefaultContentFields($contentElement, 7, 1, 'table', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 't3://page?uid=2 _blank LinkClass LinkTitle', '/page1', '_blank');

        self::assertEquals('TableCaption', $contentElement['content']['tableCaption']);
        self::assertEquals(1, $contentElement['content']['tableHeaderPosition']);
        self::assertEquals('striped', $contentElement['content']['tableClass']);
        self::assertEquals(1, $contentElement['content']['tableTfoot']);
        self::assertEquals(6, $contentElement['content']['cols']);
        self::assertIsArray($contentElement['content']['bodytext']);
        self::assertEquals($testTableContent, $contentElement['content']['bodytext']);
    }
}
