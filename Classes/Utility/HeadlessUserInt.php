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
use function json_last_error;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function substr;

use function trim;

use const JSON_ERROR_NONE;
use const PHP_VERSION_ID;

class HeadlessUserInt
{
    public const STANDARD = 'HEADLESS_INT';
    public const NESTED = 'NESTED_HEADLESS_INT';
    public const STANDARD_NULLABLE = 'HEADLESS_INT_NULL';
    public const NESTED_NULLABLE = 'NESTED_HEADLESS_INT_NULL';

    private const REGEX = '/(?P<quote>\\\\"|")?(?P<type>%s|%s)_START<<(?P<content>(?:[^>]|>(?!>(?P=type)_END))*+)>>(?P=type)_END(?P=quote)?/sS';

    /** @var array<string, string> */
    private static array $regexPatterns = [];

    public function wrap(string $content, string $type = self::STANDARD): string
    {
        return preg_replace(
            '/(' . preg_quote('<!--INT_SCRIPT.', '/') . '[0-9a-z]{32}' . preg_quote('-->', '/') . ')/',
            sprintf('%s_START<<\1>>%s_END', $type, $type),
            $content
        ) ?? $content;
    }

    public function hasNonCacheableContent(string $content): bool
    {
        return str_contains($content, self::STANDARD);
    }

    public function unwrap(string $content): string
    {
        if (str_contains($content, self::NESTED)) {
            $content = preg_replace_callback(
                $this->buildPattern(self::NESTED, self::NESTED_NULLABLE),
                fn(array $m) => $this->replace($m, $m['type'] === self::NESTED_NULLABLE),
                $content
            ) ?? $content;
        }

        return preg_replace_callback(
            $this->buildPattern(self::STANDARD, self::STANDARD_NULLABLE),
            fn(array $m) => $this->replace($m, $m['type'] === self::STANDARD_NULLABLE),
            $content
        ) ?? $content;
    }

    protected function buildPattern(string $primary, string $nullable): string
    {
        return self::$regexPatterns[$primary] ??= sprintf(
            self::REGEX,
            preg_quote($nullable, '/'),
            preg_quote($primary, '/')
        );
    }

    protected function replace(array $m, bool $isNullable): string
    {
        $hasQuotes  = $m['quote'] !== '';
        $rawContent = (string)$m['content'];

        if ($hasQuotes) {
            if ($this->isJson($rawContent)) {
                return $rawContent;
            }

            $decoded = json_decode($rawContent);

            if (empty($decoded) && $isNullable) {
                return 'null';
            }

            if ($decoded !== null) {
                return $rawContent;
            }

            return json_encode($rawContent);
        }

        $jsonEncoded = json_encode($rawContent);

        if ($jsonEncoded !== false && $jsonEncoded[0] === '"') {
            return substr($jsonEncoded, 1, -1);
        }

        return $jsonEncoded ?: '';
    }

    protected function isJson(string $string): bool
    {
        $string = trim($string);

        if ($string === '') {
            return false;
        }

        $first = $string[0];
        $last  = $string[-1];

        if (!(($first === '{' && $last === '}') || ($first === '[' && $last === ']'))) {
            return false;
        }

        if (PHP_VERSION_ID >= 80300) {
            return json_validate($string);
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
