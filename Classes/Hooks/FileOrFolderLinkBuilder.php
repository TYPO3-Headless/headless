<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * @codeCoverageIgnore
 */
class FileOrFolderLinkBuilder extends \TYPO3\CMS\Frontend\Typolink\FileOrFolderLinkBuilder
{
    /**
     * {@inheritDoc}
     * @throws UnableToLinkException
     */
    public function build(array &$linkDetails, string $linkText, string $target, array $conf): LinkResultInterface
    {
        $setup = ($GLOBALS['TSFE'] ?? null) instanceof TypoScriptFrontendController ? $GLOBALS['TSFE']->tmpl->setup : null;

        if (
            array_key_exists('type', $linkDetails)
            && $linkDetails['type'] === 'file'
            && isset($setup['plugin.']['tx_headless.']['staticTemplate'])
            && (bool)$setup['plugin.']['tx_headless.']['staticTemplate'] === true
        ) {
            $conf['forceAbsoluteUrl'] = 1;
        }

        return parent::build($linkDetails, $linkText, $target, $conf);
    }
}
