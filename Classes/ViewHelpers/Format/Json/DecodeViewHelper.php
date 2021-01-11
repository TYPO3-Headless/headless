<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

namespace FriendsOfTYPO3\Headless\ViewHelpers\Format\Json;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Converts the JSON encoded argument into a PHP variable
 */
class DecodeViewHelper extends AbstractViewHelper
{
    /**
     * Initialize
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('json', 'string', 'json to decode', false, '');
    }

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
