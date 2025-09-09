<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

/**
 * @codeCoverageIgnore
 */
class HeadlessVersion
{
    protected const VERSION = '4.7.3';

    public function getVersion(): string
    {
        return static::VERSION;
    }

    public function getMajorVersion(): int
    {
        [$explodedVersion] = explode('.', static::VERSION);
        return (int)$explodedVersion;
    }

    public function __toString(): string
    {
        return $this->getVersion();
    }
}
