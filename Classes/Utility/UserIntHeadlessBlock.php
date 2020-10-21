<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

class UserIntHeadlessBlock
{
    public function wrap(string $content): string
    {
        return \preg_replace(
            '/(' . \preg_quote('<!--INT_SCRIPT.', '/') . '[0-9a-z]{32}' . \preg_quote('-->', '/') . ')/',
            'HEADLESS_JSON_START<<\1>>HEADLESS_JSON_END',
            $content
        );
    }

    /**
     * for use in preg_replace_callback
     * to unwrap all HEADLESS_JSON_START<<>>HEADLESS_JSON_END blocks
     *
     * @param array<int, string> $input
     * @return string
     */
    public static function unwrap(array $input): string
    {
        $content = $input[2];

        if ($input[1] === $input[3] && $input[1] === '"') {
            // check if we have nested plugins
            if (\strpos($content, 'HEADLESS_JSON_START') !== false) {
                $content = \preg_replace_callback(
                    '/("|)HEADLESS_JSON_START<<(.*)>>HEADLESS_JSON_END("|)/s',
                    [__CLASS__, 'unwrap'],
                    $content
                );
            }

            // have a look inside if it might be json already
            $decoded = \json_decode($content);

            if ($decoded !== null) {
                return $content;
            }
            return \json_encode($content);
        }

        // trim one occurrence of double quotes at both ends
        $jsonEncoded = \json_encode($content);
        if ($jsonEncoded[0] === '"' && $jsonEncoded[-1] === '"') {
            $jsonEncoded = \substr($jsonEncoded, 1, -1);
        }
        return $jsonEncoded;
    }
}
