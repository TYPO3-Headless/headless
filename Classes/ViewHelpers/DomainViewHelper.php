<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ViewHelpers;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class DomainViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('return', 'string', 'value from site configuration');
    }

    public function render(): mixed
    {
        $urlUtility = GeneralUtility::makeInstance(UrlUtility::class);

        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            $urlUtility = $urlUtility->withRequest($GLOBALS['TYPO3_REQUEST']);
        }

        switch ($this->arguments['return']) {
            case 'frontendBase':
                return $urlUtility->getFrontendUrl();
            case 'proxyUrl':
                return $urlUtility->getProxyUrl();
            case 'storageProxyUrl':
                return $urlUtility->getStorageProxyUrl();
        }

        return null;
    }
}
