<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * TypolinkHook
 **/
class TypolinkHook
{
    /**
     * This Hook will convert typolinks to be used in Vue/Nuxt frontend,
     * by modifying `lastTypoLinkUrl` with json encoded details about the previously generated typolink.
     * If the static TypoScript Template won't be used, the default typolink behavior will be retained.
     *
     * @param array $params
     * @param ContentObjectRenderer $ref
     */
    public function handleLink(array $params, ContentObjectRenderer $ref): void
    {
        $setup = &$this->getTypoScriptFrontendController()->tmpl->setup;
        if (!isset($setup['plugin.']['tx_headless.']['staticTemplate'])
            || (bool)$setup['plugin.']['tx_headless.']['staticTemplate'] === false
        ) {
            // Just do nothing and don't modify the previously generated typolink when EXT:headless won't be used
            return;
        }

        $link = [
            'type' => $params['finalTagParts']['TYPE'],
            'url' => $params['finalTagParts']['url'],
            'target' => $params['finalTagParts']['targetParams'],
            'aTagParams' => $params['finalTagParts']['aTagParams'],
            'link' => $params['linktxt']
        ];

        $wrap = isset($params['conf']['wrap.'])
            ? $ref->stdWrap($params['conf']['wrap'] ?? '', $params['conf']['wrap.'])
            : $params['conf']['wrap'] ?? '';

        if ($wrap) {
            $link['link'] = $ref->wrap($link['link'], $wrap);
        }
        if ($params['linktxt'] !== '|') {
            $ref->lastTypoLinkUrl = json_encode($link);
        }
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}
