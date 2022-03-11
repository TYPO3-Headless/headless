<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\ViewHelpers\Format\Json;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Converts the JSON encoded argument into a PHP variable
 * @codeCoverageIgnore
 */
class DecodeViewHelper extends AbstractViewHelper
{
    /**
     * Initialize
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('json', 'string', 'json to decode', false, '');
        $this->registerArgument('exceptionOnFailure', 'boolean', 'Throw an exception when failing decoding the string', false, false);
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

        $object = json_decode($json, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $object;
        }

        if ($this->arguments['exceptionOnFailure'] === true) {
            throw new \Exception(sprintf(
                'Failure "%s" occured when running json_decode() for string: %s',
                json_last_error_msg(),
                $json
            ));
        }
    }
}
