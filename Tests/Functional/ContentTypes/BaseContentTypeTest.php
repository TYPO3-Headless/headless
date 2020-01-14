<?php

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
        $this->assertEquals($id, $contentElement['id'], 'id mismatch');
        $this->assertEquals($pid, $contentElement['pid'], 'pid mismatch');
        $this->assertEquals($type, $contentElement['type'], 'type mismatch');
        $this->assertEquals($colPos, $contentElement['colPos'], 'colPos mismatch');
        $this->assertEquals($categories, $contentElement['categories'], 'categories mismatch');
    }

    protected function checkAppearanceFields($contentElement, $layout = 'default', $frameClass = 'default', $spaceBefore = '', $spaceAfter = '')
    {
        $contentElementAppearance = $contentElement['appearance'];

        $this->assertEquals($layout, $contentElementAppearance['layout'], 'layout mismatch');
        $this->assertEquals($frameClass, $contentElementAppearance['frameClass'], 'frameClass mismatch');
        $this->assertEquals($spaceBefore, $contentElementAppearance['spaceBefore'], 'spaceBefore mismatch');
        $this->assertEquals($spaceAfter, $contentElementAppearance['spaceAfter'], 'spaceAfter mismatch');
    }

    protected function checkHeaderFields($contentElement, $header = '', $subheader = '', $headerLayout = 0, $headerPosition= '')
    {
        $contentElementContent = $contentElement['content'];

        $this->assertEquals($header, $contentElementContent['header'], 'header mismatch');
        $this->assertEquals($subheader, $contentElementContent['subheader'], 'subheader mismatch');
        $this->assertEquals($headerLayout, $contentElementContent['headerLayout'], 'headerLayout mismatch');
        $this->assertEquals($headerPosition, $contentElementContent['headerPosition'], 'headerPosition mismatch');
        $this->assertTrue(isset($contentElementContent['headerLink']), 'headerLink not set');
    }

    protected function checkHeaderFieldsLink($contentElement, $link, $type, $urlPrefix, $target)
    {
        $contentElementHeaderFieldsLink = $contentElement['content']['headerLink'];

        $this->assertTrue(is_array($contentElementHeaderFieldsLink), 'headerLink not an array');
        $this->assertEquals($link, $contentElementHeaderFieldsLink['link'], 'link mismatch');
        $this->assertEquals($type, $contentElementHeaderFieldsLink['type'], 'type mismatch');
        $this->assertStringStartsWith($urlPrefix, $contentElementHeaderFieldsLink['url'], 'url mismatch');
        $this->assertEquals($target, $contentElementHeaderFieldsLink['target'], 'target mismatch');
    }

    protected function checkGalleryContentFields($contentElement)
    {
        $this->assertEquals(600, $contentElement['content']['gallery']['width'], 'width mismatch');
        $this->assertEquals(10, $contentElement['content']['gallery']['columnSpacing'], 'columnSpacing mismatch');

        $this->assertTrue(is_array($contentElement['content']['gallery']['position']), 'position not set');
        $this->assertEquals('center', $contentElement['content']['gallery']['position']['horizontal'], 'position horizontal mismatch');
        $this->assertEquals('above', $contentElement['content']['gallery']['position']['vertical'], 'position vertical mismatch');
        $this->assertFalse($contentElement['content']['gallery']['position']['noWrap'], 'position noWrap mismatch');

        $this->assertTrue(is_array($contentElement['content']['gallery']['count']), 'count not set');
        $this->assertEquals(1, $contentElement['content']['gallery']['count']['files'], 'count files mismatch');
        $this->assertEquals(1, $contentElement['content']['gallery']['count']['columns'], 'count columns mismatch');
        $this->assertEquals(1, $contentElement['content']['gallery']['count']['rows'], 'count rows mismatch');

        $this->assertTrue(is_array($contentElement['content']['gallery']['border']), 'border not set');
        $this->assertFalse($contentElement['content']['gallery']['border']['enabled'], 'border enabled mismatch');
        $this->assertEquals(2, $contentElement['content']['gallery']['border']['width'], 'border width mismatch');
        $this->assertEquals(0, $contentElement['content']['gallery']['border']['padding'], 'border padding mismatch');

        $this->assertTrue(is_array($contentElement['content']['gallery']['rows']), 'rows not set');
        $this->assertEquals(1, count($contentElement['content']['gallery']['rows']), 'rows count mismatch');
        $this->assertTrue(is_array($contentElement['content']['gallery']['rows'][1]), 'rows[1] not set');
        $this->assertTrue(is_array($contentElement['content']['gallery']['rows'][1]['columns']), 'rows.columns not set');
        $this->assertEquals(1, count($contentElement['content']['gallery']['rows'][1]['columns']), 'rows.columns count mismatch');

        $this->checkGalleryFile($contentElement['content']['gallery']['rows'][1]['columns'][1], 'typo3conf/ext/headless/ext_icon.gif', 'image/gif', 'MetadataTitle', 18, 16, 1);
    }

    protected function checkGalleryFile($fileElement, $originalUrl, $mimeType, $title, $width, $height, $autoplay)
    {
        $this->assertTrue(isset($fileElement['publicUrl']), 'publicUrl not set');

        $this->assertTrue(is_array($fileElement['properties']), 'properties not set');
        $this->assertEquals($originalUrl, $fileElement['properties']['originalUrl'], 'properties originalUrl mismatch');
        $this->assertEquals($title, $fileElement['properties']['title'], 'properties title mismatch');
        $this->assertEquals($mimeType, $fileElement['properties']['mimeType'], 'properties mimeType mismatch');
        $this->assertEquals($autoplay, $fileElement['properties']['autoplay'], 'properties autoplay mismatch');

        $this->assertTrue(is_array($fileElement['properties']['dimensions']), 'properties dimensions not set');
        $this->assertEquals($width, $fileElement['properties']['dimensions']['width'], 'properties dimensions width mismatch');
        $this->assertEquals($height, $fileElement['properties']['dimensions']['height'], 'properties dimensions height mismatch');

        $this->assertTrue(is_array($fileElement['properties']['cropDimensions']), 'properties cropDimensions not set');
        $this->assertEquals($width, $fileElement['properties']['cropDimensions']['width'], 'properties cropDimensions width mismatch');
        $this->assertEquals($height, $fileElement['properties']['cropDimensions']['height'], 'properties cropDimensions height mismatch');
    }
}
