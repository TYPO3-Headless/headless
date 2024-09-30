<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;

class FileUtility
{
    public const RETINA_RATIO = 2;
    public const LQIP_RATIO = 0.1;
    protected ContentObjectRenderer $contentObjectRenderer;
    protected RendererRegistry $rendererRegistry;
    protected ImageService  $imageService;
    protected ?ServerRequestInterface $serverRequest;
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @var array<string, array<string, string>>
     */
    protected array $errors = [];
    protected Features $features;
    protected bool $allowSvgProcessing = false;

    public function __construct(
        ?ContentObjectRenderer $contentObjectRenderer = null,
        ?RendererRegistry $rendererRegistry = null,
        ?ImageService $imageService = null,
        ?ServerRequestInterface $serverRequest = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?Features $features = null
    ) {
        $this->contentObjectRenderer = $contentObjectRenderer ??
            GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->rendererRegistry = $rendererRegistry ?? GeneralUtility::makeInstance(RendererRegistry::class);
        $this->imageService = $imageService ?? GeneralUtility::makeInstance(ImageService::class);
        $this->serverRequest = $serverRequest ?? ($GLOBALS['TYPO3_REQUEST'] ?? null);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcher::class);
        $this->features = $features ?? GeneralUtility::makeInstance(Features::class);
    }

    public function setAllowSvgProcessing(bool $allowSvgProcessing): void
    {
        $this->allowSvgProcessing = $allowSvgProcessing;
    }

    /**
     * @param array<string,mixed> $dimensions
     * @return array<string, mixed>
     */
    public function processFile(
        FileInterface $fileReference,
        array $dimensions = [],
        string $cropVariant = 'default',
        bool $delayProcessing = false
    ): array {
        $originalFileReference = clone $fileReference;
        $originalFileUrl = $fileReference->getPublicUrl();
        $fileReferenceUid = $fileReference->getUid();
        $uidLocal = $fileReference->getProperty('uid_local');
        $fileRenderer = $this->rendererRegistry->getRenderer($fileReference);
        $crop = $fileReference->getProperty('crop');
        $link = $fileReference->getProperty('link');
        $linkData = null;

        if (!empty($link)) {
            $linkData = $this->contentObjectRenderer->typoLink('', ['parameter' => $link, 'returnLast' => 'result']);
            $link = $linkData instanceof LinkResultInterface ? $linkData->getUrl() : null;
        }

        $originalProperties = [
            'title' => $fileReference->getProperty('title'),
            'alternative' => $fileReference->getProperty('alternative'),
            'description' => $fileReference->getProperty('description'),
            'link' => $link === '' ? null : $link,
            'linkData' => $linkData ?? null,
        ];

        if ($fileRenderer === null && $fileReference->getType() === AbstractFile::FILETYPE_IMAGE) {
            if (!$delayProcessing && ($this->allowSvgProcessing || $fileReference->getMimeType() !== 'image/svg+xml')) {
                $fileReference = $this->processImageFile($fileReference, $dimensions, $cropVariant);
            }
            $publicUrl = $this->imageService->getImageUri($fileReference, true);
        } elseif ($fileRenderer !== null) {
            $publicUrl = $fileRenderer->render($fileReference, '', '', ['returnUrl' => true]);
        } else {
            $publicUrl = $this->getAbsoluteUrl($fileReference->getPublicUrl());
        }

        $processedProperties = [
            'mimeType' => $fileReference->getMimeType(),
            'type' => explode('/', $fileReference->getMimeType())[0],
            'filename' => $fileReference->getProperty('name'),
            'originalUrl' => $originalFileUrl,
            'uidLocal' => $uidLocal,
            'fileReferenceUid' => $fileReferenceUid,
            'size' => $this->calculateKilobytesToFileSize((int)$fileReference->getSize()),
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
            'extension' => $fileReference->getProperty('extension'),
        ];

        $event = $this->eventDispatcher->dispatch(
            new EnrichFileDataEvent(
                $originalFileReference,
                $fileReference,
                array_merge(
                    $originalProperties,
                    $processedProperties
                )
            )
        );

        $cacheBuster = '';

        if ($this->features->isFeatureEnabled('headless.assetsCacheBusting') && $event->getProperties()['type'] !== 'video') {
            $modified = $event->getProcessed()->getProperty('modification_date');

            if (!$modified) {
                $modified = $event->getProcessed()->getProperty('tstamp');
            }

            $cacheBuster = '?' . $modified;
        }

        return [
            'publicUrl' => $publicUrl . $cacheBuster,
            'properties' => $event->getProperties(),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function processImageFile(FileInterface $image, array $arguments = [], string $cropVariant = 'default'): ProcessedFile
    {
        try {
            $properties = $image->getProperties();
            $cropVariantCollection = $this->createCropVariant((string)$image->getProperty('crop'));
            $cropVariant = $cropVariant ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $arguments['width'] ?? null,
                'height' => $arguments['height'] ?? null,
                'minWidth' => $arguments['minWidth'] ?? $properties['minWidth'] ?? 0,
                'minHeight' => $arguments['minHeight'] ?? $properties['minHeight'] ?? 0,
                'maxWidth' => $arguments['maxWidth'] ?? $properties['maxWidth'] ?? 0,
                'maxHeight' => $arguments['maxHeight'] ?? $properties['maxHeight'] ?? 0,
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            if (!empty($arguments['fileExtension'])) {
                $processingInstructions['fileExtension'] = $arguments['fileExtension'];
            }
            return $this->imageService->applyProcessingInstructions($image, $processingInstructions);
        } catch (\UnexpectedValueException|\RuntimeException|\InvalidArgumentException $e) {
            $type = lcfirst(get_class($image));
            $status = get_class($e);
            $this->errors['processImageFile'][$type . '-' . $image->getUid()] = $status;
        }
    }

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

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * When retrieving the height or width for a media file
     * a possible cropping needs to be taken into account.
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
