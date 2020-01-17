<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class TextElementTest extends BaseContentTypeTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][0];

        $this->checkDefaultContentFields($contentElement, 1, 1, 'text', 0, 'SysCategory1Title,SysCategory2Title');
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 't3://page?uid=2 _blank LinkClass LinkTitle parameter=999', 'page', '/page1?parameter=999&cHash=', ' target="_blank"');

        // typolink parser was called on bodytext
        $this->assertStringContainsString('<a href="/page1?parameter=999&amp;cHash=', $contentElement['content']['bodytext']);
    }
}
