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

use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use function json_decode;
use function json_encode;

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

        $features = GeneralUtility::makeInstance(Features::class);
        $linkTarget = $params['tagAttributes']['target'] ?? '';

        $link = [
            'type' => $params['finalTagParts']['TYPE'],
            'url' => $params['finalTagParts']['url'],
            'target' => $features->isFeatureEnabled('headless.simplifiedLinkTarget') ? $linkTarget : $params['finalTagParts']['targetParams'],
            'title' => $params['tagAttributes']['title'] ?? '',
            'class' => $params['tagAttributes']['class'] ?? '',
            'link' => $params['linktxt'],
            'aTagParams' => $params['finalTagParts']['aTagParams'],
        ];

        $wrap = isset($params['conf']['wrap.'])
            ? $ref->stdWrap($params['conf']['wrap'] ?? '', $params['conf']['wrap.'])
            : $params['conf']['wrap'] ?? '';

        if ($wrap) {
            $link['link'] = $ref->wrap($link['link'], $wrap);
        }

        if ($link['type'] === 'url' && $features->isFeatureEnabled('headless.nextMajor')) {
            return;
        }

        if ($params['linktxt'] !== '|') {
            $decodedNestedTypolink = json_decode($params['finalTagParts']['url'], true);
            if (
                isset(
                    $decodedNestedTypolink['type'],
                    $decodedNestedTypolink['url'],
                    $decodedNestedTypolink['target'],
                    $decodedNestedTypolink['aTagParams'],
                    $decodedNestedTypolink['link']
                )
            ) {
                $link = $decodedNestedTypolink;
            }

            $ref->lastTypoLinkUrl = json_encode($link);
        }
    }
}
