<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Redirects;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves the public URL of a sys_redirect source (QR code, short URL): for
 * sites running in full headless mode the frontendBase is used as base URL,
 * so shared links land on the public frontend instead of the TYPO3 API host.
 * A wildcard source host ("*") resolves only when exactly one FULL headless
 * site exists — with several sites the target would be a guess.
 * Returns null when no rewrite applies (unknown host, ambiguous wildcard,
 * headless off or MIXED).
 */
class SourceUrlResolver
{
    public function __construct(
        protected readonly SiteFinder $siteFinder,
        protected readonly HeadlessModeInterface $headlessMode,
        protected readonly HeadlessFrontendUrlInterface $urlUtility,
    ) {}

    public function resolve(string $sourceHost, string $sourcePath, ServerRequestInterface $request): ?string
    {
        $site = $sourceHost === '*'
            ? $this->findSoleFullHeadlessSite()
            : $this->findSiteForSourceHost($sourceHost);

        if ($site === null) {
            return null;
        }

        $mode = (int)($site->getConfiguration()['headless'] ?? HeadlessModeInterface::NONE);

        if ($mode !== HeadlessModeInterface::FULL) {
            return null;
        }

        $boundRequest = $this->headlessMode
            ->withRequest($request)
            ->overrideBackendRequestBySite($site);

        $host = $sourceHost === '*' ? $site->getBase()->getHost() : $sourceHost;

        return $this->urlUtility
            ->withRequest($boundRequest)
            ->getFrontendUrlWithSite('https://' . $host . $sourcePath, $site);
    }

    protected function findSoleFullHeadlessSite(): ?Site
    {
        $match = null;

        foreach ($this->siteFinder->getAllSites() as $site) {
            if ((int)($site->getConfiguration()['headless'] ?? HeadlessModeInterface::NONE) !== HeadlessModeInterface::FULL) {
                continue;
            }

            if ($match !== null) {
                return null;
            }

            $match = $site;
        }

        return $match;
    }

    protected function findSiteForSourceHost(string $sourceHost): ?Site
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            if ($site->getBase()->getHost() === $sourceHost) {
                return $site;
            }

            foreach ($site->getLanguages() as $language) {
                if ($language->getBase()->getHost() === $sourceHost) {
                    return $site;
                }
            }
        }

        return null;
    }
}
