<?php

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
    public function performExtraJsonEncoding($_params, TypoScriptFrontendController $tsfe)
    {
        if ($tsfe->tmpl->setup_constants['config.']['headless.']['type.']['headless'] == $tsfe->type) {

            $tsfe->content = preg_replace_callback(
                '/("|)HEADLESS_JSON_START<<(.*?)>>HEADLESS_JSON_END("|)/s',
                function ($encodeThis) {
                    if ($encodeThis[1] === $encodeThis[3] && $encodeThis[1] === '"') {
                        // have a look inside if it might be json already
                        $decoded = json_decode($encodeThis[2]);
                        if ($decoded !== null) {
                            return $encodeThis[2];
                        } else {
                            return json_encode($encodeThis[2]);
                        }
                    } else {
                        return rtrim(ltrim(json_encode($encodeThis[2]), '"'), '"');
                    }
                },
                $tsfe->content
            );
        }
    }
}