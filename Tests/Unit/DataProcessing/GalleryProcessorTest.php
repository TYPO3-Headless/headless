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
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\FileInterface;
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
