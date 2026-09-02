<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Redirects;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Site\Entity\Site;

use function parse_str;
use function parse_url;
use function str_contains;

/**
 * Rebuilds the target URL of a matched redirect whose page target carries
 * Extbase plugin parameters in the typolink additionalParams segment
 * (`t3://page?uid=1 - - - &tx_x[controller]=X&tx_x[action]=y`). The core
 * RedirectService only applies that segment when "keep query parameters"
 * is enabled; regenerating the URI through the site router applies route
 * enhancers and cHash. Returns null when the URL resolved by the core is
 * already correct.
 */
class TargetUrlResolver
{
    public function __construct(
        protected readonly TypoLinkCodecService $typoLinkCodecService,
        protected readonly LinkService $linkService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $matchedRedirect
     */
    public function resolve(
        array $matchedRedirect,
        UriInterface $targetUrl,
        ServerRequestInterface $request
    ): ?UriInterface {
        if ($matchedRedirect['keep_query_parameters'] ?? false) {
            return null;
        }

        if ($targetUrl->getPath() === $request->getUri()->getPath()) {
            return null;
        }

        $linkParts = $this->typoLinkCodecService->decode((string)($matchedRedirect['target'] ?? ''));
        $additionalParams = (string)($linkParts['additionalParams'] ?? '');

        if (!str_contains($additionalParams, '[controller]=') || !str_contains($additionalParams, '[action]=')) {
            return null;
        }

        $site = $request->getAttribute('site');

        if (!$site instanceof Site) {
            return null;
        }

        try {
            $linkDetails = $this->linkService->resolve((string)($linkParts['url'] ?? ''));
        } catch (Exception) {
            return null;
        }

        if (($linkDetails['type'] ?? null) !== LinkService::TYPE_PAGE) {
            return null;
        }

        try {
            parse_str((string)(parse_url((string)($linkParts['url'] ?? ''), PHP_URL_QUERY) ?? ''), $typolinkData);
            parse_str($additionalParams, $params);

            $languageId = (int)($typolinkData['L'] ?? $typolinkData['_language'] ?? $linkDetails['_language'] ?? 0);

            if ($languageId > 0) {
                $params['_language'] = $site->getLanguageById($languageId);
            }

            return $site->getRouter()->generateUri($linkDetails['pageuid'], $params);
        } catch (Exception) {
            $this->logger->error(
                'Error during action redirect',
                ['record' => $matchedRedirect, 'uri' => (string)$targetUrl]
            );

            return null;
        }
    }
}
