<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer;
use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use FriendsOfTYPO3\Headless\Utility\FileUtility;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Throwable;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\MetaDataAspect;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Resource\ResourceStorage;

use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use UnexpectedValueException;

use function array_merge;

class FileUtilityTest extends UnitTestCase
{
    /**
     * @var MockObject&ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    public function testGetAbsoluteUrl(): void
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $urlDomain = 'https://test-frontend.tld';
        $normalizedParams->method('getSiteUrl')->willReturn($urlDomain . '/test-site');
        $normalizedParams->method('getRequestHost')->willReturn($urlDomain);
        $fileUtility = $this->getFileUtility($normalizedParams);

        self::assertSame(
            'https://test-frontend.tld/test-site/fileadmin/test-video-file.mp4',
            $fileUtility->getAbsoluteUrl('/fileadmin/test-video-file.mp4')
        );

        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getSiteUrl')->willReturn($urlDomain . '/test-site#asdasdas');
        $normalizedParams->method('getRequestHost')->willReturn($urlDomain);
        $fileUtility = $this->getFileUtility($normalizedParams);
        self::assertSame(
            'https://test-frontend.tld/test-site#asdasdas/fileadmin/#test-video#-file.mp#4',
            $fileUtility->getAbsoluteUrl('/fileadmin/#test-video#-file.mp#4')
        );

        $testSameUrl = 'https://test-frontend3.tld/fileadmin/test-video-file.mp4';
        self::assertSame($testSameUrl, $fileUtility->getAbsoluteUrl($testSameUrl));
    }

    public function testSetRequestDelegatesToContentObjectRenderer(): void
    {
        $request = new ServerRequest('https://test-frontend.tld/');
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->expects(self::once())->method('setRequest')->with($request);

        $fileUtility = $this->getFileUtility(null, null, $contentObjectRenderer);
        $fileUtility->setRequest($request);
    }

    public function testProcessFile()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.assetsCacheBusting'] = true;
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

        $fileReferenceData = $this->getFileReferenceBaselineData();

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

        $link = 'https://test.domain.tld/resource';
        $linkResult = new LinkResult(LinkService::TYPE_PAGE, 'https://test.domain.tld/resource');
        $file = $this->getMockFileForData($fileData, [
            'extension' => 'jpg',
            'title' => null,
            'alternative' => null,
            'description' => null,
            'link' => 123,
        ]);
        $processedFile = $this->getMockProcessedFileForData($fileData);
        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFile);
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('typoLink')->willReturn($linkResult);
        $fileUtility = $this->getFileUtility(null, $imageService, $contentObjectRenderer);
        $overwrittenBaseline = $this->getBaselineResultArrayForFile();
        $overwrittenBaseline['properties']['link'] = $link;
        $overwrittenBaseline['properties']['linkData'] = $linkResult;
        self::assertSame($overwrittenBaseline, $fileUtility->processFile($file));

        // CASE when typolink of ContentObjectRenderer returns '' instead of LinkResult
        $link = null;
        $linkResult = '';
        $file = $this->getMockFileForData($fileData, [
            'extension' => 'jpg',
            'title' => null,
            'alternative' => null,
            'description' => null,
            'link' => 123,
        ]);
        $processedFile = $this->getMockProcessedFileForData($fileData);
        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFile);
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('typoLink')->willReturn($linkResult);
        $fileUtility = $this->getFileUtility(null, $imageService, $contentObjectRenderer);
        $overwrittenBaseline = $this->getBaselineResultArrayForFile();
        $overwrittenBaseline['properties']['link'] = $link;
        $overwrittenBaseline['properties']['linkData'] = $linkResult;
        self::assertSame($overwrittenBaseline, $fileUtility->processFile($file));

        $fileReference = $this->getMockFileReferenceForData($fileReferenceData, 'video');
        $fileUtility = $this->getFileUtility();

        self::assertSame(
            $this->getBaselineResultArrayForVideoFileReference(),
            $fileUtility->processFile($fileReference)
        );
        $rendererFileUrl = 'https://renderer.public.url.tld/youtube';
        $fileReference = $this->getMockFileReferenceForData($fileReferenceData, 'video');
        $rendererRegistry = $this->createMock(RendererRegistry::class);
        $fileRenderer = $this->createMock(YouTubeRenderer::class);
        $fileRenderer->method('render')->with($fileReference, '', '', ['returnUrl' => true])->willReturn($rendererFileUrl);
        $rendererRegistry->method('getRenderer')->with($fileReference)->willReturn($fileRenderer);
        $fileUtility = $this->getFileUtility(null, null, null, $rendererRegistry);

        $overwrittenBaseline = $this->getBaselineResultArrayForVideoFileReference();
        $overwrittenBaseline['publicUrl'] = $rendererFileUrl;
        self::assertSame(
            $overwrittenBaseline,
            $fileUtility->processFile($fileReference)
        );
    }

    public function testCustomProcessingOptions(): void
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

        $fileReferenceData = $this->getFileReferenceBaselineData();

        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);
        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFile);
        $fileUtility = $this->getFileUtility(null, $imageService);

        $options = ['legacyReturn' => 0, 'cacheBusting' => 1];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'title' => null,
            'alternative' => null,
            'description' => null,
            'link' => null,
            'mimeType' => 'image/jpeg',
            'type' => 'image',
            'filename' => 'test-file.jpg',
            'originalUrl' => '/fileadmin/test-file.jpg',
            'uidLocal' => 103,
            'fileReferenceUid' => null,
            'size' => '71 KB',
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
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = ['legacyReturn' => 0, 'cacheBusting' => 1, 'properties.' => ['byType' => 1]];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'title' => null,
            'alternative' => null,
            'description' => null,
            'link' => null,
            'mimeType' => 'image/jpeg',
            'type' => 'image',
            'uidLocal' => 103,
            'fileReferenceUid' => null,
            'size' => '71 KB',
            'dimensions' =>
                [
                    'width' => 526,
                    'height' => 526,
                ],
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'includeOnly' => 'alternative,type,width,height'],
        ];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'alternative' => null,
            'type' => 'image',
            'dimensions' => [
                'width' => 526,
                'height' => 526,
            ],
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'includeOnly' => 'alternative as alt,type,width,height', 'flatten' => 1],
        ];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'alt' => null,
            'type' => 'image',
            'width' => 526,
            'height' => 526,
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'includeOnly' => 'alternative as alt,type,width,height', 'flatten' => 1],
            'autogenerate.' => [
                'urlTest' => ['factor' => 2],
            ],
        ];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'alt' => null,
            'type' => 'image',
            'width' => 526,
            'height' => 526,
            'urlTest' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'defaultFieldsByType' => 'width', 'height'],
        ];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'link' => null,
            'dimensions' => [
                'width' => 526,
                'height' => 526,
            ],
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'defaultFieldsByType' => 'width,height', 'defaultImageFields' => 'dimensions,mimeType'],
        ];

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'mimeType' => 'image/jpeg',
            'dimensions' => [
                'width' => 526,
                'height' => 526,
            ],
        ], $fileUtility->process($file, ProcessingConfiguration::fromOptions($options)));

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'includeOnly' => 'alternative as alt,type,width,height', 'flatten' => 1],
            'conditionalCropVariant' => 1,
            'autogenerate.' => [
                'urlTest' => ['factor' => 2],
            ],
        ];

        $processedFile = $fileUtility->process($file, ProcessingConfiguration::fromOptions($options));

        $file = $this->getMockFileForData(
            $fileData,
            ['crop' => '{"default":{"cropArea":{"x":1,"y":1,"width":2,"height":2},"selectedRatio":"NaN","focusArea":null},"mobile":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}']
        );

        $processedFileMock = $this->getMockProcessedFileForData($fileData);

        $cropVariantCollection = CropVariantCollection::create((string)$file->getProperty('crop'));
        $cropArea = $cropVariantCollection->getCropArea('default');

        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFileMock, [
            [
                'width' => 0,
                'height' => 0,
                'minWidth' => 0,
                'minHeight' => 0,
                'maxWidth' => 0,
                'maxHeight' => 0,
                'crop' => $cropArea,
                'fileExtension' => null,
            ],
        ]);

        $fileUtility = $this->getFileUtility(null, $imageService);

        $processedFile = $fileUtility->processCropVariants(
            $file,
            ProcessingConfiguration::fromOptions($options),
            $processedFile
        );

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'alt' => null,
            'type' => 'image',
            'width' => 526,
            'height' => 526,
            'urlTest' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'cropVariants' => [
                'default' => [
                    'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
                    'width' => 526,
                    'height' => 526,
                ],
            ],
        ], $processedFile);

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'properties.' => ['byType' => 1, 'includeOnly' => 'alternative as alt,type,width,height', 'flatten' => 1],
            'conditionalCropVariant' => 0,
            'autogenerate.' => [
                'urlTest' => ['factor' => 2],
            ],
        ];

        $processedFile = $fileUtility->process($file, ProcessingConfiguration::fromOptions($options));

        $file = $this->getMockFileForData(
            $fileData,
            ['crop' => '{"default":{"cropArea":{"x":1,"y":1,"width":2,"height":2},"selectedRatio":"NaN","focusArea":null},"mobile":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}']
        );

        $processedFileMock = $this->getMockProcessedFileForData($fileData);

        $cropVariantCollection = CropVariantCollection::create((string)$file->getProperty('crop'));
        $cropArea = $cropVariantCollection->getCropArea('default');

        $imageService = $this->getImageServiceWithProcessedFile($file, $processedFileMock, [
            [
                'width' => 0,
                'height' => 0,
                'minWidth' => 0,
                'minHeight' => 0,
                'maxWidth' => 0,
                'maxHeight' => 0,
                'crop' => $cropArea,
                'fileExtension' => null,
            ],
        ]);

        $fileUtility = $this->getFileUtility(null, $imageService);

        $processedFile = $fileUtility->processCropVariants(
            $file,
            ProcessingConfiguration::fromOptions($options),
            $processedFile
        );

        self::assertSame([
            'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'alt' => null,
            'type' => 'image',
            'width' => 526,
            'height' => 526,
            'urlTest' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'cropVariants' => [
                'default' => [
                    'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
                    'width' => 526,
                    'height' => 526,
                ],
                'mobile' => [
                    'url' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
                    'width' => 526,
                    'height' => 526,
                ],
            ],
        ], $processedFile);
    }

    public function testExceptionCatching(): void
    {
        $this->testProcessImageFileException(new UnexpectedValueException('test'));
        $this->testProcessImageFileException(new RuntimeException('test'));
        $this->testProcessImageFileException(new InvalidArgumentException('test'));
    }

    /**
     * A 100x100 source with a 75x25 crop area only contains 75x25 pixels of source
     * data inside that crop. Even with autogenerate `factor = 2`, the variant cannot
     * exceed the crop dimensions — the cap must come from the cropped area, not the
     * uncropped original. Otherwise the inner processor receives an over-sized,
     * aspect-mismatched request (e.g. 100x50 from a 3:1 crop) and produces a
     * stretched/padded image.
     */
    public function testProcessAutogenerateCapsByCroppedDimensionsNotOriginal(): void
    {
        $cropJson = '{"default":{"cropArea":{"x":0,"y":0,"width":0.75,"height":0.25},"selectedRatio":"NaN","focusArea":null}}';

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
            'crop' => $cropJson,
            'width' => 100,
            'height' => 100,
        ];

        $croppedFileData = array_merge($fileData, ['width' => 75, 'height' => 25]);

        $file = $this->getMockFileForData($fileData, ['crop' => $cropJson]);
        $processedFile = $this->getMockProcessedFileForData($croppedFileData);

        $capturedInstructions = [];
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('getImageUri')->willReturn('https://test-frontend.tld/fileadmin/test-file.jpg');
        $imageService->method('applyProcessingInstructions')->willReturnCallback(
            static function ($_file, $instructions) use (&$capturedInstructions, $processedFile) {
                $capturedInstructions[] = $instructions;
                return $processedFile;
            }
        );

        $fileUtility = $this->getFileUtility(null, $imageService);

        $options = [
            'legacyReturn' => 0,
            'cacheBusting' => 1,
            'autogenerate.' => [
                'big' => ['factor' => 2],
            ],
        ];

        $fileUtility->process($file, ProcessingConfiguration::fromOptions($options));

        self::assertCount(2, $capturedInstructions, 'Expected outer + autogenerate inner call');

        // Outer call: crop only, no explicit dimensions.
        self::assertNull($capturedInstructions[0]['width']);
        self::assertNull($capturedInstructions[0]['height']);

        // Autogenerate "big" with factor=2 on a 75x25 crop of a 100x100 image.
        // The cap should be the crop's dimensions (75x25), not the uncropped
        // file's dimensions (100x100). Without this cap, the inner request becomes
        // 100x50 — an aspect mismatch against the 3:1 crop area that the image
        // processor resolves by stretching or padding.
        self::assertSame('75', $capturedInstructions[1]['width']);
        self::assertSame('25', $capturedInstructions[1]['height']);
    }

    public function testProcessAutogenerateWithoutCropUsesFileDimensionsAsCap(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['big' => ['factor' => 2]],
        ]));

        self::assertCount(2, $captured);
        // factor=2 on a 100x100 (no crop) capped at 100x100 — can't enlarge past source pixels.
        self::assertSame('100', $captured[1]['width']);
        self::assertSame('100', $captured[1]['height']);
    }

    public function testProcessAutogenerateWithFractionalFactorScalesDown(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['lqip' => ['factor' => 0.1]],
        ]));

        // 100 * 0.1 = 10, well under the 100 cap.
        self::assertSame('10', $captured[1]['width']);
        self::assertSame('10', $captured[1]['height']);
    }

    public function testProcessAutogenerateRespectsExplicitProcessingDimensions(): void
    {
        $fileData = $this->getImageFileData(['width' => 200, 'height' => 200]);
        $processedData = array_merge($fileData, ['width' => 50, 'height' => 50]);

        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($processedData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'width' => 50,
            'height' => 50,
            'autogenerate.' => ['big' => ['factor' => 2]],
        ]));

        // Outer call carries the explicit dimensions.
        self::assertSame('50', $captured[0]['width']);
        self::assertSame('50', $captured[0]['height']);

        // Autogenerate target = explicit width/height (50), factor=2 → 100 (capped by 200 source).
        self::assertSame('100', $captured[1]['width']);
        self::assertSame('100', $captured[1]['height']);
    }

    public function testProcessAutogenerateGeneratesMultipleVariantsInOrder(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => [
                'big' => ['factor' => 2],
                'thumb' => ['factor' => 0.1],
            ],
        ]));

        // 1 outer + 2 inner.
        self::assertCount(3, $captured);
        self::assertSame('100', $captured[1]['width']); // big capped at 100
        self::assertSame('10', $captured[2]['width']);  // thumb 100*0.1=10
        self::assertArrayHasKey('big', $result);
        self::assertArrayHasKey('thumb', $result);
    }

    public function testProcessAutogenerateSkippedWhenTargetDimensionsAreZero(): void
    {
        $fileData = $this->getImageFileData(['width' => 0, 'height' => 0]);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['big' => ['factor' => 2]],
        ]));

        // Only the outer call — autogenerate loop is skipped when both targets are 0.
        self::assertCount(1, $captured);
        self::assertArrayNotHasKey('big', $result);
    }

    public function testProcessAutogenerateExpandsLegacyRetina2xAndLqipKeys(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['retina2x' => 1, 'lqip' => 1],
        ]));

        self::assertArrayHasKey('urlRetina', $result);
        self::assertArrayHasKey('urlLqip', $result);
        self::assertArrayNotHasKey('retina2x', $result);
        self::assertArrayNotHasKey('lqip', $result);
    }

    public function testProcessAutogenerateTrimsTrailingDotInVariantKey(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['big.' => ['factor' => 2]],
        ]));

        self::assertArrayHasKey('big', $result);
        self::assertArrayNotHasKey('big.', $result);
    }

    public function testProcessAutogenerateForwardsFileExtensionPerVariant(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'autogenerate.' => ['webpVariant' => ['factor' => 1, 'fileExtension' => 'webp']],
        ]));

        self::assertSame('webp', $captured[1]['fileExtension']);
    }

    public function testProcessImageFileForwardsMinMaxAndFileExtension(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'minWidth' => 10,
            'minHeight' => 20,
            'maxWidth' => 200,
            'maxHeight' => 300,
            'fileExtension' => 'png',
        ]));

        self::assertCount(1, $captured);
        self::assertSame(10, $captured[0]['minWidth']);
        self::assertSame(20, $captured[0]['minHeight']);
        self::assertSame(200, $captured[0]['maxWidth']);
        self::assertSame(300, $captured[0]['maxHeight']);
        self::assertSame('png', $captured[0]['fileExtension']);
    }

    public function testProcessSkipsImageProcessingWhenDelayProcessing(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'delayProcessing' => 1,
        ]));

        // Image processing skipped entirely.
        self::assertCount(0, $captured);
    }

    public function testProcessSkipsImageProcessingForSvgWhenProcessSvgFalse(): void
    {
        $fileData = $this->getImageFileData([
            'extension' => 'svg',
            'mime_type' => 'image/svg+xml',
            'name' => 'test-file.svg',
            'identifier' => '/test-file.svg',
        ]);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions(['legacyReturn' => 0]));

        self::assertCount(0, $captured);
    }

    public function testProcessSkipsImageProcessingForGifWhenProcessGifFalse(): void
    {
        $fileData = $this->getImageFileData([
            'extension' => 'gif',
            'mime_type' => 'image/gif',
            'name' => 'test-file.gif',
            'identifier' => '/test-file.gif',
        ]);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions(['legacyReturn' => 0]));

        self::assertCount(0, $captured);
    }

    public function testProcessSkipsImageProcessingForPdfWhenProcessPdfAsImageFalse(): void
    {
        $fileData = $this->getImageFileData([
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'name' => 'test-file.pdf',
            'identifier' => '/test-file.pdf',
        ]);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $fileUtility->process($file, ProcessingConfiguration::fromOptions(['legacyReturn' => 0]));

        self::assertCount(0, $captured);
    }

    public function testProcessCacheBusterFallsBackToTstampWhenModificationDateMissing(): void
    {
        $fileData = $this->getImageFileData([
            'modification_date' => null,
            'tstamp' => 9999,
        ]);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'cacheBusting' => 1,
        ]));

        self::assertStringEndsWith('?9999', $result['url']);
    }

    public function testProcessCropVariantsConditionalSkipsEmptyCropArea(): void
    {
        // Area::isEmpty() returns true for the full-image sentinel (0,0,1,1) — that's
        // TYPO3's "no real crop applied" marker. With conditionalCropVariant=1 those
        // are skipped. The 'default' variant has a real crop, 'mobile' is the sentinel.
        $cropJson = '{"default":{"cropArea":{"x":0.1,"y":0.1,"width":0.5,"height":0.5},"selectedRatio":"NaN","focusArea":null},'
            . '"mobile":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}';

        $fileData = $this->getImageFileData(['crop' => $cropJson]);
        $file = $this->getMockFileForData($fileData, ['crop' => $cropJson]);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $options = ['legacyReturn' => 0, 'conditionalCropVariant' => 1];
        $processed = $fileUtility->process($file, ProcessingConfiguration::fromOptions($options));
        $processed = $fileUtility->processCropVariants($file, ProcessingConfiguration::fromOptions($options), $processed);

        self::assertArrayHasKey('cropVariants', $processed);
        self::assertArrayHasKey('default', $processed['cropVariants']);
        self::assertArrayNotHasKey('mobile', $processed['cropVariants']);
    }

    public function testProcessCropVariantsOutputCropAreaIncludesCoordinates(): void
    {
        $defaultArea = ['x' => 0.1, 'y' => 0.2, 'width' => 0.5, 'height' => 0.6];
        $mobileArea = ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1];
        $cropJson = json_encode([
            'default' => ['cropArea' => $defaultArea, 'selectedRatio' => 'NaN', 'focusArea' => null],
            'mobile' => ['cropArea' => $mobileArea, 'selectedRatio' => 'NaN', 'focusArea' => null],
        ]);

        $fileData = $this->getImageFileData(['crop' => $cropJson]);
        $file = $this->getMockFileForData($fileData, ['crop' => $cropJson]);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $options = ['legacyReturn' => 0, 'outputCropArea' => 1];
        $processed = $fileUtility->process($file, ProcessingConfiguration::fromOptions($options));
        $processed = $fileUtility->processCropVariants($file, ProcessingConfiguration::fromOptions($options), $processed);

        self::assertArrayHasKey('crop', $processed['cropVariants']['default']['dimensions']);
        self::assertSame(
            ['cropArea' => $defaultArea, 'selectedRatio' => 'NaN', 'focusArea' => null],
            $processed['cropVariants']['default']['dimensions']['crop']
        );
    }

    public function testProcessOnDemandPropertiesSupportsAsAliasAndPublicUrlSkip(): void
    {
        $fileData = $this->getImageFileData(['alternative' => 'alt-text']);
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'properties.' => [
                // 'publicUrl' should be skipped, alternative aliased to 'alt', width aliased.
                'includeOnly' => 'publicUrl,alternative as alt,width',
            ],
        ]));

        self::assertArrayHasKey('alt', $result);
        self::assertSame('alt-text', $result['alt']);
        self::assertArrayNotHasKey('publicUrl', $result);
        self::assertArrayNotHasKey('alternative', $result);
        // 'width' falls under dimensions.* (not flattened).
        self::assertSame(100, $result['dimensions']['width']);
    }

    public function testProcessFlattenPropertiesPlacesWidthAtTopLevel(): void
    {
        $fileData = $this->getImageFileData();
        $file = $this->getMockFileForData($fileData);
        $processedFile = $this->getMockProcessedFileForData($fileData);

        $captured = [];
        $fileUtility = $this->getFileUtility(null, $this->createCapturingImageService($captured, $processedFile));

        $result = $fileUtility->process($file, ProcessingConfiguration::fromOptions([
            'legacyReturn' => 0,
            'properties.' => [
                'includeOnly' => 'width,height',
                'flatten' => 1,
            ],
        ]));

        self::assertSame(100, $result['width']);
        self::assertSame(100, $result['height']);
        self::assertArrayNotHasKey('dimensions', $result);
    }

    public function testGetErrorsExposesCaughtProcessingErrors(): void
    {
        $fileReference = $this->getMockFileReferenceForData($this->getFileReferenceBaselineData(), 'video');
        $imageService = $this->createPartialMock(ImageService::class, ['applyProcessingInstructions', 'getImageUri']);
        $imageService->method('getImageUri')->willReturn('');
        $imageService->method('applyProcessingInstructions')->willThrowException(new RuntimeException('processing failed'));

        $fileUtility = $this->getFileUtility(null, $imageService);
        $fileUtility->processImageFile($fileReference, ProcessingConfiguration::fromOptions([]));

        self::assertSame([RuntimeException::class], array_values($fileUtility->getErrors()['processImageFile']));
    }

    public function testFilterPropertiesUsesVideoFieldListForVideoType(): void
    {
        $properties = ['type' => 'video', 'autoplay' => 1, 'dimensions' => ['width' => 1], 'link' => '/x'];

        $filtered = (new ReflectionMethod(FileUtility::class, 'filterProperties'))->invoke(
            $this->getFileUtility(),
            ProcessingConfiguration::fromOptions(['properties.' => ['byType' => 1]]),
            $properties
        );

        self::assertSame(['type' => 'video', 'autoplay' => 1, 'dimensions' => ['width' => 1]], $filtered);
    }

    public function testFilterPropertiesUsesDefaultFieldListForOtherTypes(): void
    {
        $properties = ['type' => 'application', 'size' => 10, 'dimensions' => ['width' => 1], 'autoplay' => 1];

        $filtered = (new ReflectionMethod(FileUtility::class, 'filterProperties'))->invoke(
            $this->getFileUtility(),
            ProcessingConfiguration::fromOptions(['properties.' => ['byType' => 1]]),
            $properties
        );

        self::assertSame(['type' => 'application', 'size' => 10], $filtered);
    }

    public function testCropVariantWrapsDimensionsForLegacyReturn(): void
    {
        $file = [
            'publicUrl' => '/fileadmin/test.jpg',
            'properties' => ['dimensions' => ['width' => 100, 'height' => 50]],
        ];

        $result = (new ReflectionMethod(FileUtility::class, 'cropVariant'))->invoke(
            $this->getFileUtility(),
            ProcessingConfiguration::fromOptions([]),
            $file
        );

        self::assertSame(
            ['publicUrl' => '/fileadmin/test.jpg', 'properties' => ['dimensions' => ['width' => 100, 'height' => 50]]],
            $result
        );
    }

    public function testCropVariantFallsBackToZeroDimensionsWhenPathIsMissing(): void
    {
        $result = (new ReflectionMethod(FileUtility::class, 'cropVariant'))->invoke(
            $this->getFileUtility(),
            ProcessingConfiguration::fromOptions([]),
            ['publicUrl' => '/fileadmin/test.jpg']
        );

        self::assertSame(
            ['publicUrl' => '/fileadmin/test.jpg', 'properties' => ['dimensions' => ['width' => 0, 'height' => 0]]],
            $result
        );
    }

    protected function getFileUtility(
        ?MockObject $normalizedParams = null,
        $imageService = null,
        ?MockObject $contentObjectRenderer = null,
        ?MockObject $rendererRegistry = null
    ): FileUtility {
        if ($contentObjectRenderer === null) {
            $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        }

        if ($imageService === null) {
            $imageService = $this->createMock(ImageService::class);
        }

        if ($rendererRegistry === null) {
            $rendererRegistry = $this->createMock(RendererRegistry::class);
        }

        if ($normalizedParams === null) {
            $normalizedParams = $this->createMock(NormalizedParams::class);
            $normalizedParams->method('getSiteUrl')->willReturn('https://test-frontend.tld/test-site');
            $normalizedParams->method('getRequestHost')->willReturn('https://test-frontend.tld');
        }

        $serverRequest = $this->createMock(ServerRequest::class);
        $serverRequest->method('getAttribute')->with('normalizedParams')->willReturn($normalizedParams);

        $contentObjectRenderer->method('getRequest')->willReturn($serverRequest);

        $container = new Container();
        $listenerProvider = new ListenerProvider($container);
        $eventDispatcher = new EventDispatcher($listenerProvider);

        $fileUtility = $this->createPartialMock(FileUtility::class, ['translate']);
        $fileUtility->__construct(
            $contentObjectRenderer,
            $rendererRegistry,
            $imageService,
            $eventDispatcher,
            new Features()
        );

        $fileUtility->method('translate')->willReturnCallback(static function ($key, $extension) {
            $translated = [
                'fluid' => [
                    'viewhelper.format.bytes.units' => 'B,KB,MB,GB,TB,PB,EB,ZB,YB',
                ],
            ];

            return $translated[$extension][$key] ?? null;
        });

        return $fileUtility;
    }

    protected function getMockFileForData($data, array $overrideToArray = [])
    {
        $file = $this->createPartialMock(
            File::class,
            [
                'getMetaData',
                'getStorage',
                'toArray',
                'getProperty',
                'getUid',
                'getPublicUrl',
                'getExtension',
            ]
        );
        $file->method('getExtension')->willReturn($data['extension'] ?? '');
        $resourceStorage = $this->createMock(ResourceStorage::class);
        $resourceStorage->method('getFileInfo')->with($file)->willReturn($data);
        $metaData = $this->createMock(MetaDataAspect::class);
        $metaData->method('get')->willReturn(
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

        $file->method('getMetaData')->willReturn($metaData);
        $file->method('getStorage')->willReturn($resourceStorage);
        $file->method('getUid')->willReturn($data['uid']);
        $file->method('getPublicUrl')->willReturn('/fileadmin/test-file.jpg');
        if ($overrideToArray !== []) {
            $_data = array_merge($data, $overrideToArray);
            $file->method('getProperty')->willReturnCallback(static function ($key) use ($_data) {
                return $_data[$key] ?? null;
            });
            $file->method('toArray')->willReturn($overrideToArray);
        } else {
            $file->method('getProperty')->willReturnCallback(static function ($key) use ($data) {
                return $data[$key] ?? null;
            });
            $file->method('toArray')->willReturn(
                [
                    'extension' => 'jpg',
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                ]
            );
        }
        $file->__construct($data, $this->createMock(ResourceStorage::class));
        return $file;
    }

    protected function getMockFileReferenceForData($data, $type = 'image')
    {
        $fileReference = $this->createPartialMock(
            FileReference::class,
            [
                'getPublicUrl',
                'getUid',
                'getProperty',
                'hasProperty',
                'toArray',
                'getType',
                'getMimeType',
                'getProperties',
                'getSize',
                'getExtension',
            ]
        );
        $fileReference->method('getUid')->willReturn(103);
        if ($type === 'video') {
            $fileReference->method('getMimeType')->willReturn('video/youtube');
            $fileReference->method('getType')->willReturn(FileType::VIDEO->value);
            $fileReference->method('getPublicUrl')->willReturn('https://www.youtube.com/watch?v=123456789');
            $fileReference->method('getExtension')->willReturn('');
        } else {
            $fileReference->method('getType')->willReturn(FileType::IMAGE->value);
            $fileReference->method('getPublicUrl')->willReturn('/fileadmin/test-file.jpg');
            $fileReference->method('getMimeType')->willReturn('image/jpeg');
            $fileReference->method('getExtension')->willReturn('jpg');
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

    protected function getMockProcessedFileForData($data)
    {
        $processedFile = $this->createPartialMock(
            ProcessedFile::class,
            ['getProperty', 'getMimeType', 'getSize', 'hasProperty', 'getPublicUrl', 'getExtension']
        );
        $processedFile->method('getMimeType')->willReturn('image/jpeg');
        $processedFile->method('getSize')->willReturn($data['size']);
        $processedFile->method('getExtension')->willReturn($data['extension'] ?? 'jpg');
        $processedFile->method('getProperty')->willReturnCallback(static function ($key) use ($data) {
            return $data[$key] ?? null;
        });

        $processedFile->method('hasProperty')->willReturnCallback(static function ($key) use ($data) {
            return array_key_exists($key, $data);
        });

        return $processedFile;
    }

    protected function createCapturingImageService(
        array &$captured,
        $processedFile,
        string $publicUrl = 'https://test-frontend.tld/fileadmin/test-file.jpg'
    ) {
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('getImageUri')->willReturn($publicUrl);
        $imageService->method('applyProcessingInstructions')->willReturnCallback(
            static function ($_file, $instructions) use (&$captured, $processedFile) {
                $captured[] = $instructions;
                return $processedFile;
            }
        );

        return $imageService;
    }

    protected function getImageFileData(array $overrides = []): array
    {
        return array_merge([
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
            'width' => 100,
            'height' => 100,
        ], $overrides);
    }

    protected function getImageServiceWithProcessedFile($file, $processedFile, $processingInstruction = [])
    {
        if ($processingInstruction === []) {
            $processingInstruction = [
                'width' => 0,
                'height' => 0,
                'minWidth' => 0,
                'minHeight' => 0,
                'maxWidth' => 0,
                'maxHeight' => 0,
                'crop' => null,
                'fileExtension' => null,
            ];
        }
        $imageService = $this->createMock(ImageService::class);

        $imageService->method('getImageUri')->with($processedFile, true)->willReturn(
            'https://test-frontend.tld/fileadmin/test-file.jpg'
        );
        $imageService->method('applyProcessingInstructions')->with($file, self::anything())->willReturn($processedFile);

        return $imageService;
    }

    protected function getBaselineResultArrayForFile(): array
    {
        return [
            'publicUrl' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'properties' =>
                [
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                    'link' => null,
                    'linkData' => null,
                    'mimeType' => 'image/jpeg',
                    'type' => 'image',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => '/fileadmin/test-file.jpg',
                    'uidLocal' => 103,
                    'fileReferenceUid' => null,
                    'size' => '71 KB',
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
            'publicUrl' => 'https://test-frontend.tld/fileadmin/test-file.jpg?1639061876',
            'properties' =>
                [
                    'title' => null,
                    'alternative' => null,
                    'description' => null,
                    'link' => null,
                    'linkData' => null,
                    'mimeType' => 'image/jpeg',
                    'type' => 'image',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => '/fileadmin/test-file.jpg',
                    'uidLocal' => 103,
                    'fileReferenceUid' => 103,
                    'size' => '71 KB',
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
                    'link' => null,
                    'linkData' => null,
                    'mimeType' => 'video/youtube',
                    'type' => 'video',
                    'filename' => 'test-file.jpg',
                    'originalUrl' => 'https://www.youtube.com/watch?v=123456789',
                    'uidLocal' => 103,
                    'fileReferenceUid' => 103,
                    'size' => '71 KB',
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
                    'extension' => null,
                ],
        ];
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
            'tstamp' => 1639061876,
        ];
    }

    protected function testProcessImageFileException($exception): void
    {
        $fileReferenceData = $this->getFileReferenceBaselineData();
        $fileReference = $this->getMockFileReferenceForData($fileReferenceData, 'video');
        $imageService = $this->createPartialMock(ImageService::class, ['applyProcessingInstructions', 'getImageUri']);
        $imageService->method('getImageUri')->willReturn('');
        $imageService->method('applyProcessingInstructions')->willThrowException($exception);

        $fileUtility = $this->getFileUtility(null, $imageService);

        try {
            $fileUtility->processImageFile($fileReference, ProcessingConfiguration::fromOptions([]));
        } catch (Throwable $throwable) {
            if (!empty($fileUtility->getErrors()['processImageFile'])) {
                $errors = $fileUtility->getErrors()['processImageFile'];
                if (reset($errors) !== get_class($exception)) {
                    self::fail('Different exception triggered: ' . $errors[0]);
                }
            }
        }
    }
}
