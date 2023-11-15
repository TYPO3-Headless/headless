<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        $headlessMode = GeneralUtility::makeInstance(HeadlessMode::class)->withRequest($GLOBALS['TYPO3_REQUEST']);

        if ($headlessMode->isEnabled()) {
            $conf['forceAbsoluteUrl'] = 1;
        }

        return parent::build($linkDetails, $linkText, $target, $conf);
    }
}
