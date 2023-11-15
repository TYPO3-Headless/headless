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
final class Headless
{
    private int $mode;

    public function __construct(int $mode = HeadlessMode::NONE)
    {
        $this->mode = $mode;
    }

    public function getMode(): int
    {
        return $this->mode;
    }
}
