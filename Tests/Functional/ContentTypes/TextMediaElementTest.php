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

class TextMediaElementTest extends BaseContentTypeTest
{
    public function testTextMediaContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][0];

        $this->checkDefaultContentFields($contentElement, 2, 1, 'textmedia', 1);
        $this->checkAppearanceFields($contentElement);
        $this->checkHeaderFields($contentElement);

        // typolink parser was called on bodytext
        self::assertStringContainsString('<a href="/page1', $contentElement['content']['bodytext']);

        $this->checkGalleryContentFields($contentElement);
    }
}
