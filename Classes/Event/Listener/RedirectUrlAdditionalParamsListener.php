<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use Exception;
use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;

use TYPO3\CMS\Core\Utility\GeneralUtility;

use function parse_str;
use function str_contains;

class RedirectUrlAdditionalParamsListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly TypoLinkCodecService $typoLinkCodecService,
        private readonly LinkService $linkService,
        private readonly HeadlessFrontendUrlInterface $urlUtility,
    ) {}

    public function __invoke(RedirectUrlEvent $event): void
    {
        $request = $event->getRequest();
        $url = $event->getOriginalTargetUrl();

        if ($url->getPath() === $request->getUri()->getPath()) {
            return;
        }

        $linkParameterParts = $this->typoLinkCodecService->decode(
            (string)($event->getRedirectRecord()['target'] ?? '')
        );
        $redirectTarget = $linkParameterParts['url'] ?? '';
        $linkDetails = $this->resolveLinkDetailsFromLinkTarget($redirectTarget);
        $additionalParams = (string)($linkParameterParts['additionalParams'] ?? '');

        if (($linkDetails['type'] ?? null) === LinkService::TYPE_PAGE &&
            str_contains($additionalParams, '[action]=') &&
            str_contains($additionalParams, '[controller]=')) {
            try {
                $site = $request->getAttribute('site');
                $urlQuery = parse_url((string)($linkParameterParts['url'] ?? ''), PHP_URL_QUERY) ?? '';
                parse_str($urlQuery, $typolinkData);
                parse_str((string)$linkParameterParts['additionalParams'], $params);

                $languageId = (int)($typolinkData['L']
                    ?? $typolinkData['_language']
                    ?? $linkDetails['_language']
                    ?? 0);

                if ($languageId > 0) {
                    $language = $site->getLanguageById($languageId);
                    $params['_language'] = $language;
                }

                $frontendUrl = $this->getPageRouterForSite($site)->generateUri($linkDetails['pageuid'], $params);
                $frontendUrl = $this->urlUtility->getFrontendUrlForPage(
                    (string)$frontendUrl,
                    (int)$linkDetails['pageuid']
                );

                $event->setTargetUrl($frontendUrl);
            } catch (Exception $exception) {
                $this->logError(
                    'Error during action redirect',
                    ['record' => $event->getRedirectRecord(), 'uri' => $url]
                );
            }
        }
    }

    /**
     * @todo this metod is not fully utilized, author should take a look at it
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
     */
    protected function resolveLinkDetailsFromLinkTarget(string $redirectTarget): array
    {
        try {
            $linkDetails = $this->linkService->resolve($redirectTarget);
            switch ($linkDetails['type']) {
                case LinkService::TYPE_URL:
                    // all set up, nothing to do
                    break;
                case LinkService::TYPE_FILE:
                    /** @var File $file */
                    $file = $linkDetails['file'];
                    $linkDetails['url'] = $file->getPublicUrl();
                    break;
                case LinkService::TYPE_FOLDER:
                    /** @var Folder $folder */
                    $folder = $linkDetails['folder'];
                    $linkDetails['url'] = $folder->getPublicUrl();
                    break;
                default:
                    // we have to return the link details without having a "URL" parameter
            }
        } catch (InvalidPathException $e) {
            return [];
        }
        return $linkDetails;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getPageRouterForSite(Site $site): PageRouter
    {
        return GeneralUtility::makeInstance(PageRouter::class, $site);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param array<string, mixed> $context
     */
    protected function logError(string $message, array $context): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
