<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ContentObject;

use function json_encode;

class ExampleUserFunc
{
    public function someUserFunc(string $content, array $conf): string
    {
        return json_encode(['test2' => 'someExtraCustomData']);
    }
}
