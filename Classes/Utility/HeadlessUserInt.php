<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use function json_decode;
use function json_encode;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function substr;

class HeadlessUserInt
{
    public const STANDARD = 'HEADLESS_INT';
    public const NESTED = 'NESTED_HEADLESS_INT';
    public const STANDARD_NULLABLE = 'HEADLESS_INT_NULL';
    public const NESTED_NULLABLE = 'NESTED_HEADLESS_INT_NULL';
    private const REGEX = '/("|)%s_START<<(.*?)>>%s_END("|)/s';

    public function wrap(string $content, string $type = self::STANDARD): string
    {
        return preg_replace(
            '/(' . preg_quote('<!--INT_SCRIPT.', '/') . '[0-9a-z]{32}' . preg_quote('-->', '/') . ')/',
            sprintf('%s_START<<\1>>%s_END', $type, $type),
            $content
        );
    }

    public function unwrap(string $content): string
    {
        if (strpos($content, self::NESTED) !== false) {
            $content = preg_replace_callback(
                sprintf(self::REGEX, self::NESTED, self::NESTED),
                [$this, 'replace'],
                $content
            );
        }

        if (strpos($content, self::NESTED_NULLABLE) !== false) {
            $content = preg_replace_callback(
                sprintf(self::REGEX, self::NESTED_NULLABLE, self::NESTED_NULLABLE),
                function (array $content) {
                    return $this->replace($content, true);
                },
                $content
            );
        }

        if (strpos($content, self::STANDARD_NULLABLE) !== false) {
            $content = preg_replace_callback(
                sprintf(self::REGEX, self::STANDARD_NULLABLE, self::STANDARD_NULLABLE),
                function (array $content) {
                    return $this->replace($content, true);
                },
                $content
            );
        }

        return preg_replace_callback(
            sprintf(self::REGEX, self::STANDARD, self::STANDARD),
            [$this, 'replace'],
            $content
        );
    }

    /**
     * for use in preg_replace_callback
     * to unwrap all HEADLESS_INT<<>>HEADLESS_INT blocks
     *
     * @param array<int, string> $input
     */
    private function replace(array $input, bool $returnNull = false): ?string
    {
        $content = $input[2];
        if ($input[1] === $input[3] && $input[1] === '"') {
            // have a look inside if it might be json already
            $decoded = json_decode($content);

            if (empty($decoded) && $returnNull) {
                return json_encode(null);
            }

            if ($decoded !== null) {
                return $content;
            }
            return json_encode($content);
        }

        // trim one occurrence of double quotes at both ends
        $jsonEncoded = json_encode($content);
        if ($jsonEncoded[0] === '"' && $jsonEncoded[-1] === '"') {
            $jsonEncoded = substr($jsonEncoded, 1, -1);
        }
        return $jsonEncoded;
    }
}
