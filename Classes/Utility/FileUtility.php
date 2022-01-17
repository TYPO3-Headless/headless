<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class FileUtility
 */
class FileUtility
{
    public const RETINA_RATIO = 2;
    public const LQIP_RATIO = 0.1;

    /**
     * @var ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * @param RendererRegistry
     */
    protected $rendererRegistry;

    /**
     * @var ImageService
     */
    protected $imageService;

    /**
     * @var ServerRequestInterface
     */
    protected $serverRequest;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @param ContentObjectRenderer|null $contentObjectRenderer
     * @param RendererRegistry|null $rendererRegistry
     * @param ImageService|null $imageService
     * @param ServerRequestInterface|null $serverRequest
     */
    public function __construct(
        ?ContentObjectRenderer $contentObjectRenderer = null,
        ?RendererRegistry $rendererRegistry = null,
        ?ImageService $imageService = null,
        ?ServerRequestInterface $serverRequest = null
    ) {
        $this->contentObjectRenderer = $contentObjectRenderer ?? GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->rendererRegistry = $rendererRegistry ?? GeneralUtility::makeInstance(RendererRegistry::class);
        $this->imageService = $imageService ?? GeneralUtility::makeInstance(ImageService::class);
        $this->serverRequest = $serverRequest ?? ($GLOBALS['TYPO3_REQUEST'] ?? null);
    }

    /**
     * @param FileReference|File $fileReference
     * @param array $dimensions
     * @param string $cropVariant
     * @return array
     */
    public function processFile($fileReference, array $dimensions = [], string $cropVariant = 'default'): array
    {
        $fileReferenceUid = $fileReference->getUid();
        $uidLocal = $fileReference->getProperty('uid_local');
        $metaData = $fileReference->toArray();
        $fileRenderer = $this->rendererRegistry->getRenderer($fileReference);
        $crop = $fileReference->getProperty('crop');
        $originalFileUrl = $fileReference->getPublicUrl();

        if ($fileRenderer === null && $fileReference->getType() === AbstractFile::FILETYPE_IMAGE) {
            if ($fileReference->getMimeType() !== 'image/svg+xml') {
                $fileReference = $this->processImageFile($fileReference, $dimensions, $cropVariant);
            }
            $publicUrl = $this->imageService->getImageUri($fileReference, true);
        } elseif ($fileRenderer !== null) {
            $publicUrl = $fileRenderer->render($fileReference, '', '', ['returnUrl' => true]);
        } else {
            $publicUrl = $this->getAbsoluteUrl($fileReference->getPublicUrl());
        }

        return [
            'publicUrl' => $publicUrl,
            'properties' => [
                'title' => $metaData['title'] ?: $fileReference->getProperty('title'),
                'alternative' => $metaData['alternative'] ?: $fileReference->getProperty('alternative'),
                'description' => $metaData['description'] ?: $fileReference->getProperty('description'),
                'mimeType' => $fileReference->getMimeType(),
                'type' => explode('/', $fileReference->getMimeType())[0],
                'filename' => $fileReference->getProperty('name'),
                'originalUrl' => $originalFileUrl,
                'uidLocal' => $uidLocal,
                'fileReferenceUid' => $fileReferenceUid,
                'size' => $this->calculateKilobytesToFileSize((int)$fileReference->getSize()),
                'link' => !empty($metaData['link']) ? $this->contentObjectRenderer->typoLink_URL(
                    [
                        'parameter' => $metaData['link']
                    ]
                ) : null,
                'dimensions' => [
                    'width' => $fileReference->getProperty('width'),
                    'height' => $fileReference->getProperty('height'),
                ],
                'cropDimensions' => [
                    'width' => $this->getCroppedDimensionalProperty($fileReference, 'width', $cropVariant),
                    'height' => $this->getCroppedDimensionalProperty($fileReference, 'height', $cropVariant)
                ],
                'crop' => $crop,
                'autoplay' => $fileReference->getProperty('autoplay'),
                'extension' => $metaData['extension']
            ]
        ];
    }

    /**
     * @param FileReference|File $image
     * @param array $dimensions
     * @param string $cropVariant
     * @return ProcessedFile
     */
    public function processImageFile($image, array $dimensions = [], string $cropVariant = 'default'): ProcessedFile
    {
        try {
            $properties = $image->getProperties();
            $cropString = $properties['crop'];
            if ($image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }
            $cropVariantCollection = $this->createCropVariant((string)$cropString);
            $cropVariant = $cropVariant ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
                'minWidth' => $dimensions['minWidth'] ?? $properties['minWidth'],
                'minHeight' => $dimensions['minHeight'] ?? $properties['minHeight'],
                'maxWidth' => $dimensions['maxWidth'] ?? $properties['maxWidth'],
                'maxHeight' => $dimensions['maxHeight'] ?? $properties['maxHeight'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            return $this->imageService->applyProcessingInstructions($image, $processingInstructions);
        } catch (\UnexpectedValueException|\RuntimeException|\InvalidArgumentException $e) {
            $type = lcfirst(get_class($image));
            $status = get_class($e);
            $this->errors['processImageFile'][$type . '-' . $image->getUid()] = $status;
        }
    }

    /**
     * @param string $fileUrl
     * @return string
     */
    public function getAbsoluteUrl(string $fileUrl): string
    {
        $siteUrl = $this->getNormalizedParams()->getSiteUrl();
        $sitePath = str_replace($this->getNormalizedParams()->getRequestHost(), '', $siteUrl);
        $absoluteUrl = trim($fileUrl);
        if (stripos($absoluteUrl, 'http') !== 0) {
            $fileUrl = preg_replace('#^' . preg_quote($sitePath, '#') . '#', '', $fileUrl);
            $fileUrl = $siteUrl . $fileUrl;
        }

        return $fileUrl;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * When retrieving the height or width for a media file
     * a possible cropping needs to be taken into account.
     *
     * @param FileInterface $fileObject
     * @param string $dimensionalProperty 'width' or 'height'
     *
     * @param string $cropVariant defaults to 'default' variant
     * @return int
     */
    protected function getCroppedDimensionalProperty(
        FileInterface $fileObject,
        string $dimensionalProperty,
        string $cropVariant = 'default'
    ): int {
        if (!$fileObject->hasProperty('crop') || empty($fileObject->getProperty('crop'))) {
            return (int)$fileObject->getProperty($dimensionalProperty);
        }

        $croppingConfiguration = $fileObject->getProperty('crop');
        $cropVariantCollection = $this->createCropVariant($croppingConfiguration);
        return (int)$cropVariantCollection->getCropArea($cropVariant)->makeAbsoluteBasedOnFile($fileObject)->asArray(
        )[$dimensionalProperty];
    }

    /**
     * @param int $value
     * @return string
     */
    protected function calculateKilobytesToFileSize(int $value): string
    {
        $units = $this->translate('viewhelper.format.bytes.units', 'fluid');
        $units = GeneralUtility::trimExplode(',', $units, true);
        $bytes = max($value, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(2, 10 * $pow);

        return number_format(round($bytes, 4 * 2)) . ' ' . $units[$pow];
    }

    /**
     * @return NormalizedParams
     */
    protected function getNormalizedParams(): NormalizedParams
    {
        return $this->serverRequest->getAttribute('normalizedParams');
    }

    protected function createCropVariant(string $cropString): CropVariantCollection
    {
        return CropVariantCollection::create($cropString);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function translate(string $key, string $extensionName): ?string
    {
        return LocalizationUtility::translate($key, $extensionName);
    }
}
