<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class TableElementTest extends BaseTest
{
    public function testTextContentElement()
    {
        $testTableContent = json_decode('[["Cell1.1","Cell1.2","Cell1.3","Cell1.4","",""],["Cell2.1","","","","",""],["Cell3.1","","","","",""],["\"","","","","",""]]', true);

        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][5];

        $this->checkDefaultContentFields($contentElement, 7, 1, 'table', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 't3://page?uid=2 _blank LinkClass LinkTitle parameter=999', 'page', '/page1?parameter=999&cHash=', ' target="_blank"');

        $this->assertEquals('TableCaption', $contentElement['content']['tableCaption']);
        $this->assertEquals(1, $contentElement['content']['tableHeaderPosition']);
        $this->assertEquals('striped', $contentElement['content']['tableClass']);
        $this->assertEquals(1, $contentElement['content']['tableTfoot']);
        $this->assertEquals(6, $contentElement['content']['cols']);
        $this->assertTrue(is_array($contentElement['content']['bodytext']));
        $this->assertEquals($testTableContent, $contentElement['content']['bodytext']);
    }
}
