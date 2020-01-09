<?php

declare(strict_types=1);

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class TextElementTest extends \FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][0];

        $this->checkDefaultContentFields($contentElement, 1, 1, 'text', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->checkHeaderFields($contentElement, 'Header', 'SubHeader', 1, 2);
        $this->checkHeaderFieldsLink($contentElement, 't3://page?uid=2 _blank LinkClass LinkTitle parameter=999', 'page', '/page1?parameter=999&cHash=', ' target="_blank"');
    }
}
