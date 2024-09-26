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

class DefaultContentsTest extends BaseContentTypeTest
{
    public function testContentStructure()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);
        $contentTree = $fullTree['content'];

        self::assertTrue(isset($contentTree['colPos0']));
        self::assertTrue(count($contentTree['colPos0']) > 0);
        self::assertTrue(isset($contentTree['colPos0'][0]['appearance']));
        self::assertIsArray($contentTree['colPos0'][0]['appearance']);
        self::assertTrue(isset($contentTree['colPos1']));
        self::assertTrue(count($contentTree['colPos1']) > 0);
    }
}
