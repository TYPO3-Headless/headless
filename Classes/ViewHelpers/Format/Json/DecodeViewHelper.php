<?php
namespace FriendsOfTYPO3\Headless\ViewHelpers\Format\Json;

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

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Converts the JSON encoded argument into a PHP variable
 */
class DecodeViewHelper extends AbstractViewHelper
{
    /**
     * @param string $json
     * @return mixed
     */
    public function render($json = null)
    {
        if ($json === null) {
            $json = $this->renderChildren();
            if (empty($json)) {
                return null;
            }
        }

        return json_decode($json, true);
    }
}
