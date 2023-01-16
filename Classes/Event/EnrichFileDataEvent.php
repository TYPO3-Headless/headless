<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event;

use TYPO3\CMS\Core\Resource\FileInterface;

final class EnrichFileDataEvent
{
    /**
     * @var FileInterface|null
     */
    private ?FileInterface $fileReference;

    /**
     * @var array
     */
    private array $properties;

    public function __construct(FileInterface $fileReference, array $properties = [])
    {
        $this->fileReference = $fileReference;
        $this->properties = $properties;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * @return FileInterface|null
     */
    public function getFileReference(): ?FileInterface
    {
        return $this->fileReference;
    }
}
