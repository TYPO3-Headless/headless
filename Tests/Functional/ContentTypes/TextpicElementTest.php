<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class TextpicElementTest extends BaseContentTypeTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][2];

        $this->checkDefaultContentFields($contentElement, 11, 1, 'textpic', 1);
        $this->checkAppearanceFields($contentElement);
        $this->checkHeaderFields($contentElement);

        // typolink parser was called on bodytext
        $this->assertStringContainsString('<a href="/page1?parameter=999&amp;cHash=', $contentElement['content']['bodytext']);

        $this->checkGalleryContentFields($contentElement);
    }
}
