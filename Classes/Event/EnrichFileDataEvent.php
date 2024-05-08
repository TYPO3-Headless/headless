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

final class EnrichFileDataEvent
{
    private array $properties;

    public function __construct(
        private readonly FileInterface $originalFileReference,
        private readonly FileInterface $processedFileReference,
        private readonly ProcessingConfiguration $processingConfiguration,
        array $properties = []
    ) {
        $this->properties = $properties;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    public function getProcessed(): FileInterface
    {
        return $this->processedFileReference;
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
