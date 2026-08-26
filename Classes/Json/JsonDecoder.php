<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Json;

use JsonException;

use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function json_decode;
use function trim;

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
                $decoded = $this->tryDecode($singleData);
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

        return $this->tryDecode($possibleJson) !== null;
    }

    /**
     * @return array<mixed>|object|null
     */
    protected function tryDecode(string $value): array|object|null
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

        try {
            $decoded = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return null;
        }

        // @codeCoverageIgnoreStart
        if (!is_object($decoded) && !is_array($decoded)) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return $decoded;
    }
}
