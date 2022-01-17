<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function str_replace;

/**
 * @codeCoverageIgnore
 */
class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService
{
    /**
     * @inheritDoc
     */
    protected function getImageFromSourceString(string $src, bool $treatIdAsReference): ?FileInterface
    {
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()) {
            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class);
            $baseUriForProxy = $urlUtility->getProxyUrl();

            if ($baseUriForProxy) {
                $src = str_replace($baseUriForProxy . '/', '', $src);
            }
        }

        return parent::getImageFromSourceString($src, $treatIdAsReference);
    }
}
