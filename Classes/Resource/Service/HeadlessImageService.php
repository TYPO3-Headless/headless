<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Resource\Service;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Service\ImageService;

use function str_replace;

readonly class HeadlessImageService extends ImageService
{
    public function __construct(
        ResourceFactory $resourceFactory,
        protected HeadlessModeInterface $headlessMode,
        protected HeadlessFrontendUrlInterface $urlUtility,
    ) {
        parent::__construct($resourceFactory);
    }

    public function getImage(string $src, $image, bool $treatIdAsReference): FileInterface
    {
        return parent::getImage($this->stripProxyPrefix($src), $image, $treatIdAsReference);
    }

    protected function stripProxyPrefix(string $src): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        // headlessMode check first so we short-circuit before
        // ApplicationType::fromRequest(), which throws on requests that lack
        // the `applicationType` attribute (CLI sub-requests, malformed middleware).
        if (!$request instanceof ServerRequestInterface
            || !$this->headlessMode->isEnabledFor($request)
            || !ApplicationType::fromRequest($request)->isFrontend()
        ) {
            return $src;
        }

        $proxyUrl = $this->urlUtility->withRequest($request)->getProxyUrl();
        if ($proxyUrl === '') {
            return $src;
        }

        return str_replace($proxyUrl . '/', '', $src);
    }
}
