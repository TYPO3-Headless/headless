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

use const PHP_VERSION_ID;

class JsonDecoder implements JsonDecoderInterface
{
    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function decode(array $data): array
    {
        foreach ($data as $key => $singleData) {
            if (is_string($singleData)) {
                $decoded = $this->tryDecodeJsonString($singleData);
                if ($decoded !== null) {
                    $data[$key] = $decoded;
                }
            } elseif (is_array($singleData)) {
                $data[$key] = $this->decode($singleData);
            }
        }
        return $data;
    }

    public function isJson(mixed $possibleJson): bool
    {
        if (!is_string($possibleJson)) {
            return false;
        }

        return $this->tryDecodeJsonString($possibleJson) !== null;
    }

    /**
     * @return array<mixed>|object|null
     */
    private function tryDecodeJsonString(string $value): array|object|null
    {
        if (is_numeric($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $first = $trimmed[0];
        $last = $trimmed[-1];
        if (!(($first === '{' && $last === '}') || ($first === '[' && $last === ']'))) {
            return null;
        }

        if (PHP_VERSION_ID >= 80300 && !json_validate($trimmed)) {
            return null;
        }

        $decoded = json_decode($trimmed);

        if (!is_object($decoded) && !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
