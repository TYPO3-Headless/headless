<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class ImageElementTest extends BaseContentTypeTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][1];

        $this->checkDefaultContentFields($contentElement, 10, 1, 'image', 1);
        $this->checkAppearanceFields($contentElement);
        $this->checkHeaderFields($contentElement);

        // no bodytext
        $this->assertFalse(isset($contentElement['content']['bodytext']));

        $this->checkGalleryContentFields($contentElement);
    }
}
