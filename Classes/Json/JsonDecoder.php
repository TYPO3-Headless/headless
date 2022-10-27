<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Json;

use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function json_decode;
use function trim;

class JsonDecoder implements JsonDecoderInterface
{
    /**
     * @inheritDoc
     */
    public function decode(array $data): array
    {
        $json = [];

        foreach ($data as $key => $singleData) {
            if (is_string($singleData)) {
                if ($this->isJson($singleData)) {
                    $json[$key] = json_decode($singleData);
                } else {
                    $json[$key] = $singleData;
                }
            } elseif (is_array($singleData)) {
                $json[$key] = $this->decode($singleData);
            } else {
                $json[$key] = $singleData;
            }
        }
        return $json;
    }

    /**
     * @param mixed $possibleJson
     */
    public function isJson($possibleJson): bool
    {
        if (is_numeric($possibleJson)) {
            return false;
        }

        $possibleJson = trim((string)$possibleJson);

        if ($possibleJson === '') {
            return false;
        }

        $data = json_decode($possibleJson);

        if (!is_object($data) && !is_array($data)) {
            return false;
        }

        return $data !== null;
    }
}
