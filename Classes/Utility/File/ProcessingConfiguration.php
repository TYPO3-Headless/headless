<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility\File;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class ProcessingConfiguration
{
    private const RETINA_RATIO = 2;
    private const LQIP_RATIO = 0.1;

    public static function fromOptions(array $options): static
    {
        return new static(
            (string)($options['width'] ?? ''),
            (string)($options['height'] ?? ''),
            (int)($options['minWidth'] ?? 0),
            (int)($options['minHeight'] ?? 0),
            (int)($options['maxWidth'] ?? 0),
            (int)($options['maxHeight'] ?? 0),
            ($options['fileExtension'] ?? null),
            $options['cropVariant'] ?? 'default',
            (int)($options['conditionalCropVariant'] ?? 0) > 0,
            (int)($options['legacyReturn'] ?? 1) > 0,
            (int)($options['returnFlattenObject'] ?? 0) > 0,
            (int)($options['delayProcessing'] ?? 0) > 0,
            (int)($options['cacheBusting'] ?? 0) > 0,
            (int)($options['linkResult'] ?? 0) > 0,
            (int)($options['properties.']['flatten'] ?? 0) > 0,
            ((int)($options['properties.']['byType'] ?? 0)) > 0,
            (int)($options['processPdfAsImage'] ?? 0) > 0,
            (int)($options['processSvg'] ?? 0) > 0,
            (int)($options['outputCropArea'] ?? 0) > 0,
            GeneralUtility::trimExplode(',', $options['properties.']['includeOnly'] ?? '', true),
            GeneralUtility::trimExplode(',', $options['properties.']['defaultFieldsByType'] ?? '', true),
            GeneralUtility::trimExplode(',', $options['properties.']['defaultImageFields'] ?? '', true),
            GeneralUtility::trimExplode(',', $options['properties.']['defaultVideoFields'] ?? '', true),
            self::handleLegacyOptions($options['autogenerate.'] ?? []),
            $options
        );
    }

    private function __construct(
        public readonly string $width = '',
        public readonly string $height = '',
        public readonly int $minWidth = 0,
        public readonly int $minHeight = 0,
        public readonly int $maxWidth = 0,
        public readonly int $maxHeight = 0,
        public readonly ?string $fileExtension = null,
        public readonly string $cropVariant = 'default',
        public readonly bool $conditionalCropVariant = false,
        public readonly bool $legacyReturn = true,
        public readonly bool $flattenObject = false,
        public readonly bool $delayProcessing = false,
        public readonly bool $cacheBusting = false,
        public readonly bool $linkResult = false,
        public readonly bool $flattenProperties = false,
        public readonly bool $propertiesByType = false,
        public readonly bool $processPdfAsImage = false,
        public readonly bool $processSvg = false,
        public readonly bool $outputCropArea = false,
        public readonly array $includeProperties = [],
        public readonly array $defaultFieldsByType = [],
        public readonly array $defaultImageFields = [],
        public readonly array $defaultVideoFields = [],
        public readonly array $autogenerate = [],
        public readonly array $rawOptions = [],
    ) {}

    private static function handleLegacyOptions(array $configuration): array
    {
        if ((int)($configuration['retina2x'] ?? 0)) {
            $configuration['urlRetina'] = ['factor' => self::RETINA_RATIO];
            unset($configuration['retina2x']);
        }

        if ((int)($configuration['lqip'] ?? 0)) {
            $configuration['urlLqip'] = ['factor' => self::LQIP_RATIO];
            unset($configuration['lqip']);
        }

        return $configuration;
    }

    public function withOptions(array $options): static
    {
        return self::fromOptions(array_merge($this->rawOptions, $options));
    }
}
