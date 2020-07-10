<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

namespace FriendsOfTYPO3\Headless\ContentObject;

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
