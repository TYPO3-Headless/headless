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

class GalleryFileCollectionTest extends BaseFileCollectionTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/TypoScript/setup.typoscript',
        'EXT:headless/Tests/Functional/Fixtures/GalleryCollections.typoscript',
    ];

    public function testGalleryWithFolderAndStaticFileCollections(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $contentElement = $fullTree['content']['colPos2'][0];

        self::assertEquals(30, $contentElement['id'], 'id mismatch');
        self::assertEquals('image', $contentElement['type'], 'type mismatch');

        $gallery = $contentElement['content']['gallery'];

        self::assertEquals(2, $gallery['count']['files'], 'count files mismatch');
        self::assertEquals(2, $gallery['count']['columns'], 'count columns mismatch');
        self::assertEquals(1, $gallery['count']['rows'], 'count rows mismatch');

        $this->checkFolderCollectionFile($gallery['rows'][1]['columns'][1]);

        $staticFile = $gallery['rows'][1]['columns'][2];
        $this->checkGalleryFile($staticFile, '/typo3conf/ext/headless/ext_icon.gif', 'image/gif', 'MetadataTitle', 18, 16, 0);
        self::assertEquals(1, $staticFile['properties']['uidLocal'], 'static file uidLocal mismatch');
        self::assertEquals(4, $staticFile['properties']['fileReferenceUid'], 'static file fileReferenceUid mismatch');
    }
}
