<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * @codeCoverageIgnore
 */
class FileOrFolderLinkBuilder extends \TYPO3\CMS\Frontend\Typolink\FileOrFolderLinkBuilder
{
    protected function getHeadlessMode(): HeadlessModeInterface
    {
        return GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    /**
     * @throws UnableToLinkException
     */
    public function buildLink(
        array $linkDetails,
        array $configuration,
        ServerRequestInterface $request,
        string $linkText = '',
    ): LinkResultInterface {
        if ($this->getHeadlessMode()->withRequest($request)->isEnabled()) {
            $configuration['forceAbsoluteUrl'] = 1;
        }

        return parent::buildLink($linkDetails, $configuration, $request, $linkText);
    }
}
