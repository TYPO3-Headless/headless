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
     * @param array $data
     * @return array
     */
    public function decode(array $data): array;

    /**
     * @param mixed $possibleJson
     * @return bool
     */
    public function isJson($possibleJson): bool;
}
