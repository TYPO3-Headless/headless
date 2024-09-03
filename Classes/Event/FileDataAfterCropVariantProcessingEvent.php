<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event;

use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * @codeCoverageIgnore
 */
final class FileDataAfterCropVariantProcessingEvent
{
    private array $processedFile;

    public function __construct(
        private readonly FileInterface $originalFileReference,
        private readonly ProcessingConfiguration $processingConfiguration,
        array $processedFile = []
    ) {
        $this->processedFile = $processedFile;
    }

    public function getProcessedFile(): array
    {
        return $this->processedFile;
    }

    public function setProcessedFile(array $processedFile): void
    {
        $this->processedFile = $processedFile;
    }

    public function getOriginal(): FileInterface
    {
        return $this->originalFileReference;
    }

    public function getProcessingConfiguration(): ProcessingConfiguration
    {
        return $this->processingConfiguration;
    }
}
