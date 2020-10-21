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

use FriendsOfTYPO3\Headless\Utility\UserIntHeadlessBlock;
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
            '/("|)HEADLESS_JSON_START<<(.*)>>HEADLESS_JSON_END("|)/s',
            [UserIntHeadlessBlock::class, 'unwrap'],
            $tsfe->content
        );
    }
}
