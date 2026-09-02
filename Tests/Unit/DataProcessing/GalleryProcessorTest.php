<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\GalleryProcessor;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use FriendsOfTYPO3\Headless\Utility\FileUtilityInterface;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use ReflectionProperty;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class GalleryProcessorTest extends HeadlessUnitTestCase
{
    /**
     * @var FileUtilityInterface&MockObject
     */
    private FileUtilityInterface $fileUtility;

    /**
     * @var ImageService&MockObject
     */
    private ImageService $imageService;

    /**
     * @var ContentObjectRenderer&MockObject
     */
    private ContentObjectRenderer $contentObjectRenderer;

    private GalleryProcessor $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileUtility = $this->createMock(FileUtilityInterface::class);
        $this->imageService = $this->createMock(ImageService::class);
        $this->contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $this->contentObjectRenderer->method('getRequest')->willReturn(new ServerRequest());
        $this->contentObjectRenderer->method('stdWrapValue')->willReturnCallback(
            static fn($key, array $config, $defaultValue = '') => $config[$key] ?? $defaultValue
        );
        $this->contentObjectRenderer->data = ['imageorient' => 0, 'imagecols' => 1];

        $this->subject = new GalleryProcessor($this->fileUtility, $this->imageService);
    }

    public function testFileReferenceBackedImageIsResolvedAsFileReference(): void
    {
        $processed = ['publicUrl' => '/processed/logo.gif', 'properties' => []];

        $this->imageService->expects(self::once())
            ->method('getImage')
            ->with('103', null, true)
            ->willReturn($this->createMock(FileInterface::class));
        $this->fileUtility->expects(self::once())
            ->method('process')
            ->willReturnCallback(static function (FileInterface $image, ProcessingConfiguration $configuration) use ($processed): array {
                self::assertSame('100', $configuration->width);
                self::assertSame('50', $configuration->height);
                return $processed;
            });
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            [],
            ['files' => [$this->fileReferenceData(103, 7)]]
        );

        $gallery = $result['gallery'];

        self::assertSame(1, $gallery['count']['files']);
        self::assertSame(1, $gallery['count']['columns']);
        self::assertSame(1, $gallery['count']['rows']);
        self::assertEquals(600, $gallery['width']);
        self::assertSame('center', $gallery['position']['horizontal']);
        self::assertSame('above', $gallery['position']['vertical']);
        self::assertSame($processed, $gallery['rows'][1]['columns'][1]);
    }

    public function testNonImageFileIsPassedThroughUntouched(): void
    {
        $file = $this->fileReferenceData(104, 8, 'application');

        $this->imageService->expects(self::never())->method('getImage');
        $this->fileUtility->expects(self::never())->method('process');

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            [],
            ['files' => [$file]]
        );

        self::assertSame($file, $result['gallery']['rows'][1]['columns'][1]);
    }

    public function testFolderCollectionFileIsResolvedAsFile(): void
    {
        $processed = ['publicUrl' => '/collection/test.gif', 'properties' => []];

        $this->imageService->expects(self::once())
            ->method('getImage')
            ->with('42', null, false)
            ->willReturn($this->createMock(FileInterface::class));
        $this->fileUtility->method('process')->willReturn($processed);
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            [],
            ['files' => [$this->folderCollectionFileData(42)]]
        );

        self::assertSame($processed, $result['gallery']['rows'][1]['columns'][1]);
    }

    public function testMixedFileReferenceAndFolderCollectionFiles(): void
    {
        $this->contentObjectRenderer->data['imagecols'] = 2;

        $resolvedImages = [];
        $this->imageService->expects(self::exactly(2))
            ->method('getImage')
            ->willReturnCallback(function (string $src, $image, bool $treatIdAsReference) use (&$resolvedImages): FileInterface {
                $resolvedImages[] = [$src, $treatIdAsReference];
                return $this->createMock(FileInterface::class);
            });
        $this->fileUtility->method('process')->willReturn(['publicUrl' => '/processed.gif', 'properties' => []]);
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            [],
            ['files' => [$this->fileReferenceData(103, 7), $this->folderCollectionFileData(42)]]
        );

        self::assertSame([['103', true], ['42', false]], $resolvedImages);
        self::assertSame(2, $result['gallery']['count']['files']);
        self::assertSame(2, $result['gallery']['count']['columns']);
        self::assertSame(1, $result['gallery']['count']['rows']);
    }

    public function testEqualMediaHeightScalesMediaDimensionsAndGalleryWidth(): void
    {
        $this->contentObjectRenderer->data['imagecols'] = 2;

        $configurations = [];
        $this->fileUtility->method('process')->willReturnCallback(
            static function (FileInterface $image, ProcessingConfiguration $configuration) use (&$configurations): array {
                $configurations[] = [$configuration->width, $configuration->height];
                return ['publicUrl' => '/processed.gif', 'properties' => []];
            }
        );
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);
        $this->imageService->method('getImage')->willReturn($this->createMock(FileInterface::class));

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            ['equalMediaHeight' => '100'],
            ['files' => [$this->fileReferenceData(1, 7), $this->fileReferenceData(2, 8), $this->fileReferenceData(3, 9)]]
        );

        self::assertEquals(400, $result['gallery']['width']);
        self::assertSame([['200', '100'], ['200', '100'], ['200', '100']], $configurations);
    }

    public function testEqualMediaWidthScalesMediaDimensionsAndGalleryWidth(): void
    {
        $this->contentObjectRenderer->data['imagecols'] = 2;

        $configurations = [];
        $this->fileUtility->method('process')->willReturnCallback(
            static function (FileInterface $image, ProcessingConfiguration $configuration) use (&$configurations): array {
                $configurations[] = [$configuration->width, $configuration->height];
                return ['publicUrl' => '/processed.gif', 'properties' => []];
            }
        );
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);
        $this->imageService->method('getImage')->willReturn($this->createMock(FileInterface::class));

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            ['equalMediaWidth' => '400'],
            ['files' => [$this->fileReferenceData(1, 7), $this->fileReferenceData(2, 8)]]
        );

        self::assertEquals(600, $result['gallery']['width']);
        self::assertSame([['300', '150'], ['300', '150']], $configurations);
    }

    public function testBorderSettingsShrinkTheAvailableGalleryWidth(): void
    {
        $configurations = [];
        $this->fileUtility->method('process')->willReturnCallback(
            static function (FileInterface $image, ProcessingConfiguration $configuration) use (&$configurations): array {
                $configurations[] = [$configuration->width, $configuration->height];
                return ['publicUrl' => '/processed.gif', 'properties' => []];
            }
        );
        $this->fileUtility->method('processCropVariants')->willReturnArgument(2);
        $this->imageService->method('getImage')->willReturn($this->createMock(FileInterface::class));

        $result = $this->subject->process(
            $this->contentObjectRenderer,
            [],
            ['borderEnabled' => '1', 'borderWidth' => '3', 'borderPadding' => '2'],
            ['files' => [$this->fileReferenceData(1, 7)]]
        );

        self::assertSame(
            ['enabled' => true, 'width' => 3, 'padding' => 2],
            $result['gallery']['border']
        );
        self::assertSame([['100', '50']], $configurations);
    }

    public function testCroppedDimensionsAreCalculatedOnceFromLegacyCropConfiguration(): void
    {
        $this->setProcessorConfiguration([]);

        $fileReference = $this->createMock(FileReference::class);
        $fileReference->method('getProperty')->willReturnMap([['width', 200], ['height', 100]]);
        GeneralUtility::addInstance(FileReference::class, $fileReference);

        $processedFile = [
            'properties' => [
                'crop' => '{"default":{"cropArea":{"x":0.1,"y":0.1,"width":0.5,"height":0.5}}}',
                'fileReferenceUid' => 7,
                'uidLocal' => 3,
                'dimensions' => ['width' => 200, 'height' => 100],
            ],
        ];

        self::assertSame(100, $this->getCroppedDimension($processedFile, 'width'));
        self::assertSame(50, $this->getCroppedDimension($processedFile, 'height'));
    }

    public function testCroppedDimensionsWithoutCropReadFlatDimensionsWhenLegacyReturnIsDisabled(): void
    {
        $this->setProcessorConfiguration(['legacyReturn' => 0]);

        $processedFile = [
            'crop' => null,
            'uidLocal' => 3,
            'dimensions' => ['width' => 123, 'height' => 45],
        ];

        self::assertSame(123, $this->getCroppedDimension($processedFile, 'width'));
        self::assertSame(45, $this->getCroppedDimension($processedFile, 'height'));
    }

    public function testCroppedDimensionsFromCropConfigurationWhenLegacyReturnIsDisabled(): void
    {
        $this->setProcessorConfiguration(['legacyReturn' => 0]);

        $fileReference = $this->createMock(FileReference::class);
        $fileReference->method('getProperty')->willReturnMap([['width', 300], ['height', 200]]);
        GeneralUtility::addInstance(FileReference::class, $fileReference);

        $processedFile = [
            'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":0.5,"height":0.5}}}',
            'uidLocal' => 3,
            'dimensions' => ['width' => 300, 'height' => 200],
        ];

        self::assertSame(150, $this->getCroppedDimension($processedFile, 'width'));
        self::assertSame(100, $this->getCroppedDimension($processedFile, 'height'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function setProcessorConfiguration(array $options): void
    {
        (new ReflectionProperty(GalleryProcessor::class, 'processorConfigurationObject'))
            ->setValue($this->subject, ProcessingConfiguration::fromOptions($options));
    }

    /**
     * @param array<string, mixed> $processedFile
     */
    private function getCroppedDimension(array $processedFile, string $property): int
    {
        return (new ReflectionMethod($this->subject, 'getCroppedDimensionalPropertyFromProcessedFile'))
            ->invoke($this->subject, $processedFile, $property);
    }

    /**
     * @return array<string, mixed>
     */
    private function fileReferenceData(int $fileReferenceUid, int $uidLocal, string $type = 'image'): array
    {
        return [
            'publicUrl' => '/fileadmin/logo.gif',
            'properties' => [
                'type' => $type,
                'mimeType' => 'image/gif',
                'fileReferenceUid' => $fileReferenceUid,
                'uidLocal' => $uidLocal,
                'crop' => null,
                'dimensions' => ['width' => 100, 'height' => 50],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function folderCollectionFileData(int $sysFileUid): array
    {
        return [
            'publicUrl' => '/collection/test.gif',
            'properties' => [
                'type' => 'image',
                'mimeType' => 'image/gif',
                'fileReferenceUid' => null,
                'uidLocal' => $sysFileUid,
                'crop' => null,
                'dimensions' => ['width' => 100, 'height' => 50],
            ],
        ];
    }
}
