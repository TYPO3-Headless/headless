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

class EnrichFileDataEvent
{
    /**
     * @var array<string, mixed>
     */
    protected array $properties;

    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        protected readonly FileInterface $originalFileReference,
        protected readonly FileInterface $processedFileReference,
        protected readonly ProcessingConfiguration $processingConfiguration,
        array $properties = []
    ) {
        $this->properties = $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array<string, mixed> $properties
     */
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
