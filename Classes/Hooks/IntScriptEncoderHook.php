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

namespace FriendsOfTYPO3\Headless\Hooks;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class IntScriptEncoderHook
{
    /**
     * Handle json encoding of content which was placed here via INT_SCRIPT replacement.
     *
     * @param $_params
     * @param TypoScriptFrontendController $tsfe
     */
    public function performExtraJsonEncoding($_params, TypoScriptFrontendController $tsfe): void
    {
        if (!isset($tsfe->tmpl->setup['plugin.']['tx_headless.']['staticTemplate'])
            || (bool)$tsfe->tmpl->setup['plugin.']['tx_headless.']['staticTemplate'] === false
        ) {
            // Just do nothing
            return;
        }

        $tsfe->content = \preg_replace_callback(
            '/("|)HEADLESS_JSON_START<<(.*?)>>HEADLESS_JSON_END("|)/s',
            static function ($encodeThis) {
                if ($encodeThis[1] === $encodeThis[3] && $encodeThis[1] === '"') {
                    // have a look inside if it might be json already
                    $decoded = \json_decode($encodeThis[2]);
                    if ($decoded !== null) {
                        return $encodeThis[2];
                    }
                    return \json_encode($encodeThis[2]);
                }

                // trim one occurence of double quotes at both ends
                $jsonEncoded = \json_encode($encodeThis[2]);
                if ($jsonEncoded[0] === '"' && $jsonEncoded[-1] === '"') {
                    $jsonEncoded = \substr($jsonEncoded, 1, -1);
                }
                return $jsonEncoded;
            },
            $tsfe->content
        );
    }
}
