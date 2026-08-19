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

class UploadsFileCollectionTest extends BaseFileCollectionTesting
{
    public function testUploadsWithFolderAndStaticFileCollections(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos2'][1];

        self::assertEquals(31, $contentElement['id'], 'id mismatch');
        self::assertEquals('uploads', $contentElement['type'], 'type mismatch');

        $files = $contentElement['content']['media'];

        self::assertIsArray($files, 'media not an array');
        self::assertCount(2, $files, 'media count mismatch');

        $this->checkFolderCollectionFile($files[0]);

        $staticFile = $files[1];
        self::assertEquals('/typo3conf/ext/headless/ext_icon.gif', $staticFile['properties']['originalUrl'], 'static file originalUrl mismatch');
        self::assertEquals('MetadataTitle', $staticFile['properties']['title'], 'static file title mismatch');
        self::assertEquals(1, $staticFile['properties']['uidLocal'], 'static file uidLocal mismatch');
        self::assertEquals(4, $staticFile['properties']['fileReferenceUid'], 'static file fileReferenceUid mismatch');
    }
}
