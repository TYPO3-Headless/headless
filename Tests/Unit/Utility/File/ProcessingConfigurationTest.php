<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility\File;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;

class ProcessingConfigurationTest extends HeadlessUnitTestCase
{
    public function testDefaults(): void
    {
        $configuration = ProcessingConfiguration::fromOptions([]);

        self::assertSame('', $configuration->width);
        self::assertSame('', $configuration->height);
        self::assertSame(0, $configuration->minWidth);
        self::assertSame(0, $configuration->maxHeight);
        self::assertNull($configuration->fileExtension);
        self::assertSame('default', $configuration->cropVariant);
        self::assertFalse($configuration->conditionalCropVariant);
        self::assertTrue($configuration->legacyReturn);
        self::assertFalse($configuration->flattenObject);
        self::assertFalse($configuration->cacheBusting);
        self::assertSame([], $configuration->includeProperties);
        self::assertSame([], $configuration->autogenerate);
        self::assertSame([], $configuration->rawOptions);
    }

    public function testOptionsAreMappedAndCast(): void
    {
        $options = [
            'width' => '300c',
            'height' => 200,
            'minWidth' => '10',
            'maxWidth' => '1920',
            'fileExtension' => 'webp',
            'cropVariant' => 'mobile',
            'legacyReturn' => '0',
            'returnFlattenObject' => '1',
            'cacheBusting' => 1,
            'processSvg' => '1',
            'outputCropArea' => 1,
            'properties.' => [
                'flatten' => '1',
                'byType' => 1,
                'includeOnly' => 'title, alt ,,',
            ],
        ];

        $configuration = ProcessingConfiguration::fromOptions($options);

        self::assertSame('300c', $configuration->width);
        self::assertSame('200', $configuration->height);
        self::assertSame(10, $configuration->minWidth);
        self::assertSame(1920, $configuration->maxWidth);
        self::assertSame('webp', $configuration->fileExtension);
        self::assertSame('mobile', $configuration->cropVariant);
        self::assertFalse($configuration->legacyReturn);
        self::assertTrue($configuration->flattenObject);
        self::assertTrue($configuration->cacheBusting);
        self::assertTrue($configuration->processSvg);
        self::assertTrue($configuration->outputCropArea);
        self::assertTrue($configuration->flattenProperties);
        self::assertTrue($configuration->propertiesByType);
        self::assertSame(['title', 'alt'], $configuration->includeProperties);
        self::assertSame($options, $configuration->rawOptions);
    }

    public function testLegacyAutogenerateOptionsAreMigrated(): void
    {
        $configuration = ProcessingConfiguration::fromOptions([
            'autogenerate.' => [
                'retina2x' => '1',
                'lqip' => 1,
                'custom' => ['factor' => 3],
            ],
        ]);

        self::assertSame(
            [
                'custom' => ['factor' => 3],
                'urlRetina' => ['factor' => 2],
                'urlLqip' => ['factor' => 0.1],
            ],
            $configuration->autogenerate
        );
    }

    public function testDisabledLegacyAutogenerateOptionsAreKept(): void
    {
        $configuration = ProcessingConfiguration::fromOptions([
            'autogenerate.' => ['retina2x' => '0', 'lqip' => 0],
        ]);

        self::assertSame(['retina2x' => '0', 'lqip' => 0], $configuration->autogenerate);
    }

    public function testWithOptionsMergesOntoRawOptions(): void
    {
        $base = ProcessingConfiguration::fromOptions(['width' => '100', 'cropVariant' => 'mobile']);
        $derived = $base->withOptions(['height' => '50', 'cropVariant' => 'desktop']);

        self::assertSame('100', $derived->width);
        self::assertSame('50', $derived->height);
        self::assertSame('desktop', $derived->cropVariant);

        // Original stays untouched.
        self::assertSame('', $base->height);
        self::assertSame('mobile', $base->cropVariant);
    }
}
