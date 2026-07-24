<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event;

use FriendsOfTYPO3\Headless\Event\FileDataAfterCropVariantProcessingEvent;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use TYPO3\CMS\Core\Resource\FileInterface;

class FileDataAfterCropVariantProcessingEventTest extends HeadlessUnitTestCase
{
    public function testEventExposesConstructorValuesAndAllowsReplacingProcessedFile(): void
    {
        $original = $this->createMock(FileInterface::class);
        $configuration = ProcessingConfiguration::fromOptions(['cropVariant' => 'mobile']);

        $event = new FileDataAfterCropVariantProcessingEvent($original, $configuration, ['url' => '/a.jpg']);

        self::assertSame($original, $event->getOriginal());
        self::assertSame($configuration, $event->getProcessingConfiguration());
        self::assertSame(['url' => '/a.jpg'], $event->getProcessedFile());

        $event->setProcessedFile(['url' => '/b.jpg', 'custom' => 1]);

        self::assertSame(['url' => '/b.jpg', 'custom' => 1], $event->getProcessedFile());
    }
}
