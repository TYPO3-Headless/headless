<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\ContentTypes;

use FriendsOfTYPO3\Headless\Test\Functional\BaseTest;

abstract class BaseContentTypeTest extends BaseTest
{
    /**
     * set up objects
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/../Fixtures/content.xml');
    }

    protected function checkDefaultContentFields($contentElement, $id, $pid, $type, $colPos = 0, $categories = '')
    {
        self::assertEquals($id, $contentElement['id'], 'id mismatch');
        self::assertEquals($pid, $contentElement['pid'], 'pid mismatch');
        self::assertEquals($type, $contentElement['type'], 'type mismatch');
        self::assertEquals($colPos, $contentElement['colPos'], 'colPos mismatch');
        self::assertEquals($categories, $contentElement['categories'], 'categories mismatch');
    }

    protected function checkAppearanceFields($contentElement, $layout = 'default', $frameClass = 'default', $spaceBefore = '', $spaceAfter = '')
    {
        $contentElementAppearance = $contentElement['appearance'];

        self::assertEquals($layout, $contentElementAppearance['layout'], 'layout mismatch');
        self::assertEquals($frameClass, $contentElementAppearance['frameClass'], 'frameClass mismatch');
        self::assertEquals($spaceBefore, $contentElementAppearance['spaceBefore'], 'spaceBefore mismatch');
        self::assertEquals($spaceAfter, $contentElementAppearance['spaceAfter'], 'spaceAfter mismatch');
    }

    protected function checkHeaderFields($contentElement, $header = '', $subheader = '', $headerLayout = 0, $headerPosition= '')
    {
        $contentElementContent = $contentElement['content'];

        self::assertEquals($header, $contentElementContent['header'], 'header mismatch');
        self::assertEquals($subheader, $contentElementContent['subheader'], 'subheader mismatch');
        self::assertEquals($headerLayout, $contentElementContent['headerLayout'], 'headerLayout mismatch');
        self::assertEquals($headerPosition, $contentElementContent['headerPosition'], 'headerPosition mismatch');
        self::assertTrue(isset($contentElementContent['headerLink']), 'headerLink not set');
    }

    protected function checkHeaderFieldsLink($contentElement, $link, $type, $urlPrefix, $target)
    {
        $contentElementHeaderFieldsLink = $contentElement['content']['headerLink'];

        self::assertTrue(is_array($contentElementHeaderFieldsLink), 'headerLink not an array');
        self::assertEquals($link, $contentElementHeaderFieldsLink['link'], 'link mismatch');
        self::assertEquals($type, $contentElementHeaderFieldsLink['type'], 'type mismatch');
        self::assertStringStartsWith($urlPrefix, $contentElementHeaderFieldsLink['url'], 'url mismatch');
        self::assertEquals($target, $contentElementHeaderFieldsLink['target'], 'target mismatch');
    }

    protected function checkGalleryContentFields($contentElement)
    {
        self::assertEquals(600, $contentElement['content']['gallery']['width'], 'width mismatch');
        self::assertEquals(10, $contentElement['content']['gallery']['columnSpacing'], 'columnSpacing mismatch');

        self::assertTrue(is_array($contentElement['content']['gallery']['position']), 'position not set');
        self::assertEquals('center', $contentElement['content']['gallery']['position']['horizontal'], 'position horizontal mismatch');
        self::assertEquals('above', $contentElement['content']['gallery']['position']['vertical'], 'position vertical mismatch');
        self::assertFalse($contentElement['content']['gallery']['position']['noWrap'], 'position noWrap mismatch');

        self::assertTrue(is_array($contentElement['content']['gallery']['count']), 'count not set');
        self::assertEquals(1, $contentElement['content']['gallery']['count']['files'], 'count files mismatch');
        self::assertEquals(1, $contentElement['content']['gallery']['count']['columns'], 'count columns mismatch');
        self::assertEquals(1, $contentElement['content']['gallery']['count']['rows'], 'count rows mismatch');

        self::assertTrue(is_array($contentElement['content']['gallery']['border']), 'border not set');
        self::assertFalse($contentElement['content']['gallery']['border']['enabled'], 'border enabled mismatch');
        self::assertEquals(2, $contentElement['content']['gallery']['border']['width'], 'border width mismatch');
        self::assertEquals(0, $contentElement['content']['gallery']['border']['padding'], 'border padding mismatch');

        self::assertTrue(is_array($contentElement['content']['gallery']['rows']), 'rows not set');
        self::assertEquals(1, count($contentElement['content']['gallery']['rows']), 'rows count mismatch');
        self::assertTrue(is_array($contentElement['content']['gallery']['rows'][1]), 'rows[1] not set');
        self::assertTrue(is_array($contentElement['content']['gallery']['rows'][1]['columns']), 'rows.columns not set');
        self::assertEquals(1, count($contentElement['content']['gallery']['rows'][1]['columns']), 'rows.columns count mismatch');

        $this->checkGalleryFile($contentElement['content']['gallery']['rows'][1]['columns'][1], 'typo3conf/ext/headless/ext_icon.gif', 'image/gif', 'MetadataTitle', 18, 16, 1);
    }

    protected function checkGalleryFile($fileElement, $originalUrl, $mimeType, $title, $width, $height, $autoplay)
    {
        self::assertTrue(isset($fileElement['publicUrl']), 'publicUrl not set');

        self::assertTrue(is_array($fileElement['properties']), 'properties not set');
        self::assertEquals($originalUrl, $fileElement['properties']['originalUrl'], 'properties originalUrl mismatch');
        self::assertEquals($title, $fileElement['properties']['title'], 'properties title mismatch');
        self::assertEquals($mimeType, $fileElement['properties']['mimeType'], 'properties mimeType mismatch');
        self::assertEquals($autoplay, $fileElement['properties']['autoplay'], 'properties autoplay mismatch');

        self::assertTrue(is_array($fileElement['properties']['dimensions']), 'properties dimensions not set');
        self::assertEquals($width, $fileElement['properties']['dimensions']['width'], 'properties dimensions width mismatch');
        self::assertEquals($height, $fileElement['properties']['dimensions']['height'], 'properties dimensions height mismatch');

        self::assertTrue(is_array($fileElement['properties']['cropDimensions']), 'properties cropDimensions not set');
        self::assertEquals($width, $fileElement['properties']['cropDimensions']['width'], 'properties cropDimensions width mismatch');
        self::assertEquals($height, $fileElement['properties']['cropDimensions']['height'], 'properties cropDimensions height mismatch');
    }
}
