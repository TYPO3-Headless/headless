<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function rtrim;
use function str_replace;

class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService
{
    /**
     * @inheritDoc
     */
    protected function getImageFromSourceString(string $src, bool $treatIdAsReference): ?FileInterface
    {
        if ($this->environmentService->isEnvironmentInFrontendMode()) {
            $conf = $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getConfiguration();
            $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);
            $baseUriForProxy = rtrim($frontendBase->resolveWithVariants(
                $conf['frontendApiProxy'] ?? '',
                $conf['baseVariants'] ?? null,
                'frontendApiProxy'
            ), '/');

            if ($baseUriForProxy) {
                $src = str_replace($baseUriForProxy . '/', '', $src);
            }
        }

        return parent::getImageFromSourceString($src, $treatIdAsReference);
    }
}
