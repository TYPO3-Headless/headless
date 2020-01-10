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
}
