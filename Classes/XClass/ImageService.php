<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function str_replace;

/**
 * @codeCoverageIgnore
 */
readonly class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService
{
    private HeadlessModeInterface $headlessMode;
    private HeadlessFrontendUrlInterface $urlUtility;

    public function __construct(ResourceFactory $resourceFactory)
    {
        parent::__construct($resourceFactory);
        $this->headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class);
        $this->urlUtility = GeneralUtility::makeInstance(HeadlessFrontendUrlInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function getImageFromSourceString(string $src, bool $treatIdAsReference): ?FileInterface
    {
        $headlessMode = $this->headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
            && $headlessMode->isEnabled()
        ) {
            $urlUtility = $this->urlUtility->withRequest($GLOBALS['TYPO3_REQUEST']);
            $baseUriForProxy = $urlUtility->getProxyUrl();

            if ($baseUriForProxy) {
                $src = str_replace($baseUriForProxy . '/', '', $src);
            }
        }

        return parent::getImageFromSourceString($src, $treatIdAsReference);
    }
}
