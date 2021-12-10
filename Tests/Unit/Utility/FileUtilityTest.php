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

namespace FriendsOfTYPO3\Headless\Test\Unit\Utility;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\MetaDataAspect;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FileUtilityTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    public function testGetAbsoluteUrl(): void
    {
        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $urlDomain = 'https://test-frontend.tld';
        $normalizedParams->getSiteUrl()->shouldBeCalled(1)->willReturn($urlDomain . '/test-site');
        $normalizedParams->getRequestHost()->shouldBeCalled(1)->willReturn($urlDomain);
        $fileUtility = $this->getFileUtility($normalizedParams);

        self::assertSame(
            'https://test-frontend.tld/test-site/fileadmin/test-video-file.mp4',
            $fileUtility->getAbsoluteUrl('/fileadmin/test-video-file.mp4')
        );

        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $normalizedParams->getSiteUrl()->shouldBeCalled(1)->willReturn($urlDomain . '/test-site#asdasdas');
        $normalizedParams->getRequestHost()->shouldBeCalled(1)->willReturn($urlDomain);
        $fileUtility = $this->getFileUtility($normalizedParams);
        self::assertSame(
            'https://test-frontend.tld/test-site#asdasdas/fileadmin/#test-video#-file.mp#4',
            $fileUtility->getAbsoluteUrl('/fileadmin/#test-video#-file.mp#4')
        );

        $testSameUrl = 'https://test-frontend3.tld/fileadmin/test-video-file.mp4';
        self::assertSame($testSameUrl, $fileUtility->getAbsoluteUrl($testSameUrl));
    }

    public function testProcessFile()
    {
        $fileData = [
            'uid' => 103,
            'pid' => 0,
            'missing' => 0,
            'type' => '2',
            'storage' => 1,
            'identifier' => '/test-file.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'name' => 'test-file.jpg',
            'size' => 72392,
            'creation_date' => 1639061876,
            'modification_date' => 1639061876,
            'crop' => null,
            'width' => 526,
            'height' => 526,
        ];

        $fileReferenceData = [
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

        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);
        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFile);
        $fileUtility = $this->getFileUtility(null, $imageService);

        self::assertSame($this->getBaselineResultArrayForFile(), $fileUtility->processFile($file));

        $fileReference = $this->getMockFileReferenceForData($fileReferenceData);
        $processedFile = $this->getMockProcessedFileForData($fileReferenceData);
        $imageService = $this->getImageServiceWithProcessedFile($fileReference, $processedFile);
        $fileUtility = $this->getFileUtility(null, $imageService);

        self::assertSame($this->getBaselineResultArrayForFileReference(), $fileUtility->processFile($fileReference));

        $fileReference = $this->getMockFileReferenceForData($fileReferenceData, 'video');
        $fileUtility = $this->getFileUtility();

        self::assertSame($this->getBaselineResultArrayForVideoFileReference(), $fileUtility->processFile($fileReference));
    }

    protected function getFileUtility(
        ?ObjectProphecy $normalizedParams = null,
        ?ObjectProphecy $imageService = null
    ): FileUtility {
        $contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        if ($imageService === null) {
            $imageService = $this->prophesize(ImageService::class);
        }
        $rendererRegistry = $this->prophesize(RendererRegistry::class);

        if ($normalizedParams === null) {
            $normalizedParams = $this->prophesize(NormalizedParams::class);
            $normalizedParams->getSiteUrl()->willReturn('https://test-frontend.tld/test-site');
            $normalizedParams->getRequestHost()->willReturn('https://test-frontend.tld');
        }

        $serverRequest = $this->prophesize(ServerRequest::class);
        $serverRequest->getAttribute('normalizedParams')->willReturn($normalizedParams->reveal());

        $fileUtility = $this->createPartialMock(FileUtility::class, ['translate']);
        $fileUtility->__construct(
            $contentObjectRenderer->reveal(),
            $rendererRegistry->reveal(),
            $imageService->reveal(),
            $serverRequest->reveal()
        );

        $fileUtility->method('translate')->willReturnCallback(static function ($key, $extension) {
            $translated = [
                'fluid' => [
                    'viewhelper.format.bytes.units' => 'B,KB,MB,GB,TB,PB,EB,ZB,YB'
                ]
            ];

            return $translated[$extension][$key] ?? null;
        });

        return $fileUtility;
    }

    protected function getMockFileForData($data)
    {
        $file = $this->createPartialMock(
            File::class,
            [
                'getMetaData',
                'getStorage',
                'toArray',
                'getProperty',
                'getUid',
                'getPublicUrl'
            ]
        );
        $resourceStorage = $this->prophesize(ResourceStorage::class);
        $resourceStorage->getFileInfo($file)->willReturn($data);
        $metaData = $this->prophesize(MetaDataAspect::class);
        $metaData->get()->willReturn(
            [
                'width' => $data['width'],
                'height' => $data['height'],
                'crop' => null,
                'minWidth' => null,
                'maxWidth' => null,
                'minHeight' => null,
                'maxHeight' => null,
            ]
        );

        $file->method('getMetaData')->willReturn($metaData->reveal());
        $file->method('getStorage')->willReturn($resourceStorage->reveal());
        $file->method('getUid')->willReturn($data['uid']);
        $file->method('getPublicUrl')->willReturn('/fileadmin/test-file.jpg');
        $file->method('toArray')->willReturn(
            [
                'extension' => 'jpg',
                'title' => null,
                'alternative' => null,
                'description' => null,
            ]
        );
        $file->__construct($data, $this->prophesize(ResourceStorage::class)->reveal());
        return $file;
    }

    protected function getMockFileReferenceForData($data, $type = 'image')
    {
        $fileReference = $this->createPartialMock(
            FileReference::class,
            ['getPublicUrl', 'getUid', 'getProperty', 'toArray', 'getType', 'getMimeType', 'getProperties', 'getSize']
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

        $fileReference->method('toArray')->willReturn($data);
        $fileReference->method('getProperties')->willReturn($data);
        $fileReference->method('getSize')->willReturn($data['size']);
        return $fileReference;
    }

    protected function getMockProcessedFileForData($data)
    {
        $processedFile = $this->createPartialMock(
            ProcessedFile::class,
            ['getProperty', 'getMimeType', 'getSize', 'hasProperty', 'getPublicUrl']
        );
        $processedFile->method('getMimeType')->willReturn('image/jpeg');
        $processedFile->method('getSize')->willReturn($data['size']);
        $processedFile->method('hasProperty')->willReturn(false);
        $processedFile->method('getProperty')->willReturnCallback(static function ($key) use ($data) {
            return $data[$key] ?? null;
        });

        return $processedFile;
    }

    protected function getImageServiceWithProcessedFile($file, $processedFile, $processingInstruction = [])
    {
        if ($processingInstruction === []) {
            $processingInstruction = [
                'width' => null,
                'height' => null,
                'minWidth' => null,
                'minHeight' => null,
                'maxWidth' => null,
                'maxHeight' => null,
                'crop' => null,
            ];
        }
        $imageService = $this->prophesize(ImageService::class);

        $imageService->getImageUri($processedFile, true)->willReturn(
            'https://test-frontend.tld/fileadmin/test-file.jpg'
        );
        $imageService->applyProcessingInstructions($file, $processingInstruction)->willReturn($processedFile);

        return $imageService;
    }

    protected function getBaselineResultArrayForFile(): array
    {
        return [
            'publicUrl' => 'https://test-frontend.tld/fileadmin/test-file.jpg',
            'properties' =>
                [
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                    'mimeType' => 'image/jpeg',
                    'type' => 'image',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => '/fileadmin/test-file.jpg',
                    'uidLocal' => null,
                    'fileReferenceUid' => 103,
                    'size' => '71 KB',
                    'link' => null,
                    'dimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'cropDimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'crop' => null,
                    'autoplay' => null,
                    'extension' => 'jpg',
                ],
        ];
    }

    protected function getBaselineResultArrayForFileReference(): array
    {
        return [
            'publicUrl' => 'https://test-frontend.tld/fileadmin/test-file.jpg',
            'properties' =>
                [
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                    'mimeType' => 'image/jpeg',
                    'type' => 'image',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => '/fileadmin/test-file.jpg',
                    'uidLocal' => 103,
                    'fileReferenceUid' => 103,
                    'size' => '71 KB',
                    'link' => null,
                    'dimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'cropDimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}',
                    'autoplay' => 0,
                    'extension' => 'jpg',
                ],
        ];
    }

    protected function getBaselineResultArrayForVideoFileReference(): array
    {
        return [
            'publicUrl' => 'https://www.youtube.com/watch?v=123456789',
            'properties' =>
                [
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                    'mimeType' => 'video/youtube',
                    'type' => 'video',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => 'https://www.youtube.com/watch?v=123456789',
                    'uidLocal' => 103,
                    'fileReferenceUid' => 103,
                    'size' => '71 KB',
                    'link' => null,
                    'dimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'cropDimensions' =>
                        [
                            'width' => 526,
                            'height' => 526,
                        ],
                    'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}',
                    'autoplay' => 0,
                    'extension' => 'jpg',
                ],
        ];
    }
}
