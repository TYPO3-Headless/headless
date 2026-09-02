<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit;

use ReflectionProperty;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Shared base for headless unit tests.
 *
 * Centralises the {@see GeneralUtility::$container} reset that used to be
 * duplicated across ~10 test files. Subclasses that need to register
 * services should override {@see setUp()}, call `parent::setUp()`, build
 * their container, and pass it to `GeneralUtility::setContainer(...)`.
 */
abstract class HeadlessUnitTestCase extends UnitTestCase
{
    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }
}
