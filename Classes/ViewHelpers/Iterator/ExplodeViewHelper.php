<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\ViewHelpers\Iterator;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function constant;
use function explode;
use function str_contains;
use function strlen;
use function trim;

/**
 * Explode ViewHelper
 * Explodes a string by $glue
 * @codeCoverageIgnore
 */
class ExplodeViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('content', 'string', 'String to be exploded by glue)', false, '');
        $this->registerArgument('glue', 'string', 'String used as glue in the string to be exploded. Use glue value of "constant:NAMEOFCONSTANT" (fx "constant:LF" for linefeed as glue)', false, ',');
        $this->registerArgument('as', 'string', 'Template variable name to assign. If not specified returns the result array instead');
    }

    public function render(): mixed
    {
        $content = $this->arguments['content'];
        $as = $this->arguments['as'];
        $glue = $this->resolveGlue();
        $contentWasSource = false;
        if (empty($content) === true) {
            $content = $this->renderChildren();
            $contentWasSource = true;
        }
        $output = explode($glue, (string)$content);
        if (empty($as) === true || $contentWasSource === true) {
            return $output;
        }
        return trim($content);
    }

    /**
     * Detects the proper glue string to use for implode/explode operation
     *
     * @return string
     */
    protected function resolveGlue(): string
    {
        $glue = $this->arguments['glue'];
        if (str_contains($glue, ':') && strlen($glue) > 1) {
            // glue contains a special type identifier, resolve the actual glue
            [$type, $value] = explode(':', $glue);
            switch ($type) {
                case 'constant':
                    $glue = constant($value);
                    break;
                default:
                    $glue = $value;
            }
        }
        return $glue;
    }
}
