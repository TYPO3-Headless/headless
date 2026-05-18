<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;

interface FileUtilityInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function processFile(
        FileInterface $fileReference,
        array $arguments = [],
        string $cropVariant = 'default',
        bool $delayProcessing = false
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function process(FileInterface $fileReference, ProcessingConfiguration $processingConfiguration): array;

    public function processImageFile(
        FileInterface $fileReference,
        ProcessingConfiguration $processingConfiguration
    ): ?ProcessedFile;

    /**
     * @param array<string, mixed> $processedFile
     * @return array<string, mixed>
     */
    public function processCropVariants(
        FileInterface $originalFileReference,
        ProcessingConfiguration $processingConfiguration,
        array $processedFile
    ): array;

    public function getAbsoluteUrl(string $fileUrl): string;

    /**
     * @return array<string, array<string, string>>
     */
    public function getErrors(): array;
}
