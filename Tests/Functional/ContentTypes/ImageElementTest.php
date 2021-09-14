<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseContentTypeTest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class ImageElementTest extends BaseContentTypeTest
{
    public function testImageContentElement()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1'][1];

        $this->checkDefaultContentFields($contentElement, 10, 1, 'image', 1);
        $this->checkAppearanceFields($contentElement);
        $this->checkHeaderFields($contentElement);

        // no bodytext
        self::assertFalse(isset($contentElement['content']['bodytext']));

        $this->checkGalleryContentFields($contentElement);
    }
}
