<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ViewHelpers;

use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class DebugViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\DebugViewHelper
{
    /**
     * A wrapper for \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump().
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $dump = DebuggerUtility::var_dump($renderChildrenClosure(), $arguments['title'], $arguments['maxDepth'], (bool)$arguments['plainText'], (bool)$arguments['ansiColors'], (bool)$arguments['inline'], $arguments['blacklistedClassNames'], $arguments['blacklistedPropertyNames']);
        echo $dump;
        die();
    }
}
