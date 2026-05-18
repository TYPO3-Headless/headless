<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ViewHelpers\Format\Json;

use JsonException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Converts the JSON encoded argument into a PHP variable
 * @codeCoverageIgnore
 */
class DecodeViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('json', 'string', 'json to decode', false);
    }

    public function render(): mixed
    {
        $json = $this->arguments['json'];
        if ($json === null) {
            $json = $this->renderChildren();
            if ($json !== null) {
                $json = trim((string)$json);
            }
            if (empty($json)) {
                return null;
            }
        }

        try {
            return json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if ($GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] ?? false) {
                throw $e;
            }
            return null;
        }
    }
}
