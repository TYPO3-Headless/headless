<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\ContentTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class DefaultElementTest extends BaseContentTypeTesting
{
    public function testElementWithoutRenderingDefinition()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos1']['14'];

        $this->checkDefaultContentFields($contentElement, 33, 1, 'invalid_ctype', 1);

        self::assertEquals(
            'Content Element with uid "33" and type "invalid_ctype" has no rendering definition!',
            $contentElement['content']['error']
        );
    }
}
