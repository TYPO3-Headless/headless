<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class HtmlElementTest extends BaseContentTypeTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][2];

        $this->checkDefaultContentFields($contentElement, 4, 1, 'html', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->assertFalse(isset($contentElement['content']['subheader']));

        // typolink parser was NOT called on bodytext
        $this->assertEquals('<a href="t3://page?uid=2 _blank LinkClass LinkTitle parameter=999">Link</a>', $contentElement['content']['bodytext']);
    }
}
