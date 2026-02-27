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
use function is_object;
use function is_string;
use function json_decode;
use function json_last_error;
use function trim;

use const JSON_ERROR_NONE;
use const PHP_VERSION_ID;

class JsonDecoder implements JsonDecoderInterface
{
    /**
     * @inheritDoc
     */
    public function decode(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                if ($value !== '' && ($value[0] === '{' || $value[0] === '[')) {
                    $decoded = json_decode($value);
                    $result[$key] = (is_object($decoded) || is_array($decoded)) ? $decoded : $value;
                } else {
                    $result[$key] = $value;
                }
            } elseif (is_array($value)) {
                $result[$key] = $this->decode($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param mixed $possibleJson
     */
    public function isJson(mixed $possibleJson): bool
    {
        if (!is_string($possibleJson) || $possibleJson === '') {
            return false;
        }

        $trimmed = trim($possibleJson);

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return false;
        }

        if (PHP_VERSION_ID >= 80300) {
            return json_validate($possibleJson);
        }

        json_decode($possibleJson);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
