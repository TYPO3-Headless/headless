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
 * BOOL Content object only for use in JSON content object
 *
 * ** not working ** outside of JSON content object
 */
class BooleanContentObject extends AbstractContentObject
{
    /**
     * Rendering the cObject, JSON
     * @param array $conf Array of TypoScript properties
     * @return bool
     */
    public function render($conf = []): bool
    {
        if (!is_array($conf)) {
            return false;
        }
        $content = false;
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
        return (bool)((int)$content);
    }
}
