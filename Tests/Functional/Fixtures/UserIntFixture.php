<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\Fixtures;

use TYPO3\CMS\Core\Attribute\AsAllowedCallable;

class UserIntFixture
{
    #[AsAllowedCallable]
    public function direct(): string
    {
        return 'direct-resolved';
    }

    #[AsAllowedCallable]
    public function nested(): string
    {
        return 'nested-resolved';
    }
}
