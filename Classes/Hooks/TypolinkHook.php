<?php
declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\Hooks;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
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
     * @param array $params
     * @param ContentObjectRenderer $ref
     */
    public function handleLink(array $params, ContentObjectRenderer $ref): void
    {
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
}
