<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use FriendsOfTYPO3\Headless\XClass\Typolink\LinkResult;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class TypolinkHook
{
    public function handleLink(array $params, ContentObjectRenderer $ref): void
    {
        if (!($GLOBALS['TSFE'] instanceof TypoScriptFrontendController)) {
            return;
        }

        $setup = $GLOBALS['TSFE']->tmpl->setup;

        if (!isset($setup['plugin.']['tx_headless.']['staticTemplate'])
            || (bool)$setup['plugin.']['tx_headless.']['staticTemplate'] === false
        ) {
            // Just do nothing and don't modify the previously generated typolink when EXT:headless won't be used
            return;
        }

        $ref->lastTypoLinkResult = (new LinkResult('', ''))->withAttributes($ref->lastTypoLinkResult->getAttributes());
    }
}
