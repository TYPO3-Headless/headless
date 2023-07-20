<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event;

use FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class EnrichFileDataEventTest extends UnitTestCase
{
    use ProphecyTrait;

    public function testEvent()
    {
        $properties = [
            'prop-1' => 'value-1',
            'prop-2' => 'value-2',
        ];
        $fileReferenceMock = $this->getMockFileReferenceForData($this->getFileReferenceBaselineData());
        $enrichFileDataEvent = new EnrichFileDataEvent(clone $fileReferenceMock, $fileReferenceMock, $properties);

        self::assertEquals($fileReferenceMock, $enrichFileDataEvent->getOriginal());
        self::assertSame($fileReferenceMock, $enrichFileDataEvent->getProcessed());
        self::assertSame($properties, $enrichFileDataEvent->getProperties());

        $overwriteProperties = $enrichFileDataEvent->getProperties();
        $overwriteProperties['prop-1'] = 'value-overwritten';
        $overwriteProperties['prop-3'] = 'value-3';
        $enrichFileDataEvent->setProperties($overwriteProperties);

        self::assertSame($overwriteProperties, $enrichFileDataEvent->getProperties());
    }

    protected function getFileReferenceBaselineData(): array
    {
        return [
            'extension' => 'jpg',
            'size' => 72392,
            'title' => null,
            'description' => null,
            'alternative' => null,
            'name' => 'test-file.jpg',
            'link' => '',
            'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}',
            'autoplay' => 0,
            'minWidth' => null,
            'minHeight' => null,
            'maxWidth' => null,
            'maxHeight' => null,
            'width' => 526,
            'uid_local' => 103,
            'height' => 526,
        ];
    }

    protected function getMockFileReferenceForData($data, $type = 'image')
    {
        $fileReference = $this->createPartialMock(
            FileReference::class,
            ['getPublicUrl', 'getUid', 'getProperty', 'hasProperty', 'toArray', 'getType', 'getMimeType', 'getProperties', 'getSize']
        );
        $fileReference->method('getUid')->willReturn(103);
        if ($type === 'video') {
            $fileReference->method('getMimeType')->willReturn('video/youtube');
            $fileReference->method('getType')->willReturn(AbstractFile::FILETYPE_VIDEO);
            $fileReference->method('getPublicUrl')->willReturn('https://www.youtube.com/watch?v=123456789');
        } else {
            $fileReference->method('getType')->willReturn(AbstractFile::FILETYPE_IMAGE);
            $fileReference->method('getPublicUrl')->willReturn('/fileadmin/test-file.jpg');
            $fileReference->method('getMimeType')->willReturn('image/jpeg');
        }

        $fileReference->method('getProperty')->willReturnCallback(static function ($key) use ($data) {
            return $data[$key] ?? null;
        });

        $fileReference->method('hasProperty')->willReturnCallback(static function ($key) use ($data) {
            return array_key_exists($key, $data);
        });

        $fileReference->method('toArray')->willReturn($data);
        $fileReference->method('getProperties')->willReturn($data);
        $fileReference->method('getSize')->willReturn($data['size']);
        return $fileReference;
    }
}
