<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\ContentTypes;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class BaseFileCollectionTesting extends BaseContentTypeTesting
{
    protected const COLLECTION_FOLDER = '/collection_gallery';
    protected const COLLECTION_FILE = '/collection_gallery/test.gif';
    private const ONE_PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/file_collections.csv');

        $folder = Environment::getPublicPath() . self::COLLECTION_FOLDER;
        GeneralUtility::mkdir_deep($folder);
        file_put_contents(
            Environment::getPublicPath() . self::COLLECTION_FILE,
            base64_decode(self::ONE_PIXEL_GIF)
        );
    }

    /**
     * @param array<string, mixed> $fileElement
     */
    protected function checkFolderCollectionFile(array $fileElement): void
    {
        self::assertStringEndsWith(self::COLLECTION_FILE, $fileElement['publicUrl'], 'folder file publicUrl mismatch');
        self::assertEquals(self::COLLECTION_FILE, $fileElement['properties']['originalUrl'], 'folder file originalUrl mismatch');
        self::assertEquals('image/gif', $fileElement['properties']['mimeType'], 'folder file mimeType mismatch');
        self::assertEquals('test.gif', $fileElement['properties']['filename'], 'folder file filename mismatch');
        self::assertNull($fileElement['properties']['fileReferenceUid'], 'folder file fileReferenceUid not null');
        self::assertNotEmpty($fileElement['properties']['uidLocal'], 'folder file uidLocal missing');
        self::assertEquals(1, $fileElement['properties']['dimensions']['width'], 'folder file width mismatch');
        self::assertEquals(1, $fileElement['properties']['dimensions']['height'], 'folder file height mismatch');
    }
}
