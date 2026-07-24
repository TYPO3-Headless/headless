<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessVersion;

class HeadlessVersionTest extends HeadlessUnitTestCase
{
    public function testMajorVersionIsFirstSegmentOfVersion(): void
    {
        $version = new class extends HeadlessVersion {
            protected const VERSION = '7.2.1-rc3';
        };

        self::assertSame('7.2.1-rc3', $version->getVersion());
        self::assertSame(7, $version->getMajorVersion());
        self::assertSame('7.2.1-rc3', (string)$version);
    }

    public function testShippedVersionIsConsistent(): void
    {
        $version = new HeadlessVersion();

        self::assertSame($version->getVersion(), (string)$version);
        self::assertGreaterThanOrEqual(5, $version->getMajorVersion());
    }
}
