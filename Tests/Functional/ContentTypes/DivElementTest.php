<?php

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class DivElementTest extends BaseTest
{
    public function testTextContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos0'][3];

        $this->checkDefaultContentFields($contentElement, 5, 1, 'div', 0);
        $this->checkAppearanceFields($contentElement, 'layout-1', 'Frame', 'SpaceBefore', 'SpaceAfter');
        $this->assertEquals('Header', $contentElement['content']['header']);
        $this->assertFalse(isset($contentElement['content']['subheader']));
        $this->assertFalse(isset($contentElement['content']['bodytext']));
    }
}
