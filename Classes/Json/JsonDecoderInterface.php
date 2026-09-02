<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Json;

interface JsonDecoderInterface
{
    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function decode(array $data): array;

    public function isJson(mixed $possibleJson): bool;
}
