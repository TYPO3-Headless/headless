<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

/**
 * FLOAT Content Object only for use in JSON content object
 *
 * ** not working ** outside of JSON content object
 */
class FloatContentObject extends AbstractContentObject
{
    /**
     * @param array<string, mixed> $conf Array of TypoScript properties
     */
    public function render($conf = []): float
    {
        if (!is_array($conf)) {
            return 0.0;
        }
        $content = 0.0;
        if (isset($conf['value'])) {
            $content = $conf['value'];
            unset($conf['value']);
        }
        if (isset($conf['value.'])) {
            $content = $this->cObj->stdWrap($content, $conf['value.']);
            unset($conf['value.']);
        }
        if (!empty($conf)) {
            $content = $this->cObj->stdWrap($content, $conf);
        }
        return (float)$content;
    }
}
