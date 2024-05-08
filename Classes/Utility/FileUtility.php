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
use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;

use function array_key_exists;
use function array_merge;
use function in_array;

class FileUtility
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $errors = [];

    public function __construct(
        private readonly ContentObjectRenderer $contentObjectRenderer,
        private readonly RendererRegistry $rendererRegistry,
        private readonly ImageService $imageService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Features $features
    ) {}

    public function processFile(
        FileInterface $fileReference,
        array $arguments = [],
        string $cropVariant = 'default',
        bool $delayProcessing = false
    ): array {
        $arguments['legacyReturn'] = 1;
        $arguments['delayProcessing'] = $delayProcessing;
        $arguments['cropVariant'] = $cropVariant;

        return $this->process($fileReference, ProcessingConfiguration::fromOptions($arguments));
    }

    public function process(FileInterface $fileReference, ProcessingConfiguration $processingConfiguration): array
    {
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

        if (!$processingConfiguration->legacyReturn) {
            unset($originalProperties['linkData']);
            $originalProperties['link'] = $processingConfiguration->linkResult ? $linkData : $link;
        }

        if ($fileRenderer === null && GeneralUtility::inList(
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
            $fileReference->getExtension()
        )) {
            if (!$processingConfiguration->delayProcessing && $fileReference->getMimeType() !== 'image/svg+xml') {
                $fileReference = $this->processImageFile($fileReference, $processingConfiguration);
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
                'width' => $this->getCroppedDimensionalProperty(
                    $fileReference,
                    'width',
                    $processingConfiguration->cropVariant
                ),
                'height' => $this->getCroppedDimensionalProperty(
                    $fileReference,
                    'height',
                    $processingConfiguration->cropVariant
                ),
            ],
            'crop' => $crop,
            'autoplay' => $fileReference->getProperty('autoplay'),
            'extension' => $fileReference->getProperty('extension'),
        ];

        $processedProperties = array_merge(
            $originalProperties,
            $processedProperties
        );

        if ($processingConfiguration->propertiesByType) {
            $processedProperties = $this->filterProperties($processedProperties);
        }

        $event = $this->eventDispatcher->dispatch(
            new EnrichFileDataEvent(
                $originalFileReference,
                $fileReference,
                $processingConfiguration,
                $processedProperties
            )
        );

        $processedProperties = $event->getProperties();

        if ($processingConfiguration->includeProperties !== []) {
            $processedProperties = $this->onDemandProperties($processingConfiguration, $processedProperties);
        }

        $cacheBuster = '';

        if (($this->features->isFeatureEnabled('headless.assetsCacheBusting') || $processingConfiguration->cacheBusting) &&
            !in_array($fileReference->getMimeType(), ['video/youtube', 'video/vimeo'], true)) {
            $modified = $event->getProcessed()->getProperty('modification_date');

            if (!$modified) {
                $modified = $event->getProcessed()->getProperty('tstamp');
            }

            $cacheBuster = '?' . $modified;
        }

        $processedFile = [($processingConfiguration->legacyReturn ? 'publicUrl' : 'url') => $publicUrl . $cacheBuster];

        if ($processingConfiguration->legacyReturn && !isset($processedProperties['properties'])) {
            $processedProperties = ['properties' => $processedProperties];
        }

        $processedFile = array_merge($processedFile, $processedProperties);

        if ($processingConfiguration->autogenerate !== []) {
            $processedFile = $this->processAutogenerate($originalFileReference, $fileReference, $processedFile, $processingConfiguration);
        }

        return $processedFile;
    }

    private function onDemandProperties(ProcessingConfiguration $processingConfiguration, array $properties): array
    {
        $processed = [];
        $props = [];

        foreach ($processingConfiguration->includeProperties as $prop) {
            if ($prop === 'publicUrl') {
                continue;
            }

            $propName = $prop;

            if (str_contains($prop, ' as ')) {
                [$prop, $propName] = GeneralUtility::trimExplode(' as ', $prop, true);

                if ($propName === '') {
                    $propName = $prop;
                }
            }

            if (in_array($prop, ['width', 'height'], true)) {
                $value = $properties['dimensions'][$prop] ?? 0;

                if ($processingConfiguration->flattenProperties) {
                    $props[$propName] = $value;
                } else {
                    $props['dimensions'][$propName] = $value;
                }
            } else {
                $props[$propName] = $properties[$prop] ?? null;
            }
        }

        return array_merge($processed, $props);
    }

    private function filterProperties(array $properties): array
    {
        $allowedDefault = ['type', 'size', 'title', 'alternative', 'description', 'uidLocal', 'fileReferenceUid', 'mimeType'];
        $allowedForImages = array_merge($allowedDefault, ['dimensions', 'link', 'linkData']);
        $allowedForVideo = array_merge($allowedDefault, ['dimensions', 'autoplay', 'originalUrl']);

        $allowed = match ($properties['type']) {
            'video' => $allowedForVideo,
            'image' => $allowedForImages,
            default => $allowedDefault,
        };

        $filtered = [];
        foreach (array_keys($properties) as $property) {
            if (in_array($property, $allowed, true) && array_key_exists($property, $properties)) {
                $filtered[$property] = $properties[$property];
            }
        }

        return $filtered;
    }

    public function processImageFile(
        FileInterface $fileReference,
        ProcessingConfiguration $processingConfiguration
    ): ProcessedFile {
        try {
            $cropVariantCollection = $this->createCropVariant((string)$fileReference->getProperty('crop'));
            $cropArea = $cropVariantCollection->getCropArea($processingConfiguration->cropVariant);

            return $this->imageService->applyProcessingInstructions($fileReference, [
                'width' => $processingConfiguration->width,
                'height' => $processingConfiguration->height,
                'minWidth' => $processingConfiguration->minWidth,
                'minHeight' => $processingConfiguration->minHeight,
                'maxWidth' => $processingConfiguration->maxWidth,
                'maxHeight' => $processingConfiguration->maxWidth,
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($fileReference),
                'fileExtension' => $processingConfiguration->fileExtension,
            ]);
        } catch (\UnexpectedValueException|\RuntimeException|\InvalidArgumentException $e) {
            $type = lcfirst(get_class($fileReference));
            $status = get_class($e);
            $this->errors['processImageFile'][$type . '-' . $fileReference->getUid()] = $status;
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
        return (int)$cropVariantCollection->getCropArea($cropVariant)->makeAbsoluteBasedOnFile($fileObject)->asArray()[$dimensionalProperty];
    }

    protected function calculateKilobytesToFileSize(int $value): string
    {
        $units = $this->translate('viewhelper.format.bytes.units', 'fluid');
        $units = GeneralUtility::trimExplode(',', $units, true);
        $bytes = max($value, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 2 ** (10 * $pow);

        return number_format(round($bytes, 4 * 2)) . ' ' . $units[$pow];
    }

    protected function getNormalizedParams(): NormalizedParams
    {
        return $this->contentObjectRenderer->getRequest()->getAttribute('normalizedParams');
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

    private function processAutogenerate(FileInterface $originalReference, FileInterface $fileReference, array $processedFile, ProcessingConfiguration $processingConfiguration): array
    {
        $originalWidth = $originalReference->getProperty('width');
        $originalHeight = $originalReference->getProperty('height');
        $targetWidth = $processingConfiguration->width > 0 ? $processingConfiguration->width : $fileReference->getProperty('width');
        $targetHeight = $processingConfiguration->height > 0 ? $processingConfiguration->height : $fileReference->getProperty('height');

        if ($targetWidth || $targetHeight) {
            foreach ($processingConfiguration->autogenerate as $autogenerateKey => $conf) {
                $autogenerateKey = rtrim($autogenerateKey, '.');
                $factor = (float)($conf['factor'] ?? 1.0);

                $processedFile[$autogenerateKey] = $this->process(
                    $originalReference,
                    $processingConfiguration->withOptions(
                        [
                            'fileExtension' => $conf['fileExtension'] ?? null,
                            // multiply width/height by factor,
                            // but don't stretch image beyond its original dimensions!
                            'width' => min($targetWidth * $factor, $originalWidth),
                            'height' => min($targetHeight * $factor, $originalHeight),
                            'autogenerate.' => null,
                            'legacyReturn' => 0,
                        ]
                    )
                )['url'];
            }
        }

        return $processedFile;
    }

    public function processCropVariants(
        FileInterface $originalFileReference,
        ProcessingConfiguration $processingConfiguration,
        array $processedFile
    ): array {
        /**
         * @var string|null $crop
         */
        $crop = $originalFileReference->getProperty('crop');

        if ($crop !== null) {
            unset($processedFile['crop'], $processedFile['properties']['crop']);

            $cropVariants = json_decode($originalFileReference->getProperty('crop'), true);

            $collection = CropVariantCollection::create($originalFileReference->getProperty('crop'));

            if (is_array($cropVariants) && count($cropVariants) > 1 && str_starts_with($originalFileReference->getMimeType(), 'image/')) {
                foreach (array_keys($cropVariants) as $cropVariantName) {
                    if ($processingConfiguration->conditionalCropVariant && $collection->getCropArea($cropVariantName)->isEmpty()) {
                        continue;
                    }

                    $file = $this->process($originalFileReference, $processingConfiguration);
                    $processedFile['cropVariants'][$cropVariantName] = $this->cropVariant($processingConfiguration, $file);
                }
            }
        }

        return $processedFile;
    }

    private function cropVariant(ProcessingConfiguration $processingConfiguration, array $file): array
    {
        $url = $processingConfiguration->legacyReturn ? $file['publicUrl'] : $file['url'];
        $urlKey = $processingConfiguration->legacyReturn ? 'publicUrl' : 'url';

        $path = '';

        if ($processingConfiguration->legacyReturn) {
            $path .= 'properties/';
        }

        if (!$processingConfiguration->flattenProperties) {
            $path .= 'dimensions/';
        }

        $dimensions = [
            'width' => ArrayUtility::getValueByPath($file, $path . 'width'),
            'height' => ArrayUtility::getValueByPath($file, $path . 'height'),
        ];

        if (!$processingConfiguration->legacyReturn && $processingConfiguration->flattenProperties) {
            return array_merge([$urlKey => $url], $dimensions);
        }

        $wrappedDimensions = $dimensions;

        if (!$processingConfiguration->flattenProperties) {
            $wrappedDimensions = ['dimensions' => $wrappedDimensions];
        }

        if ($processingConfiguration->legacyReturn) {
            $wrappedDimensions = ['properties' => $wrappedDimensions];
        }

        return [$urlKey => $url, ...$wrappedDimensions];
    }
}
