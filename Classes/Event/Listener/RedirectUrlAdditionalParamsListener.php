<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\XClass\Routing\PageRouter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;

use function parse_str;
use function strpos;

class RedirectUrlAdditionalParamsListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private TypoLinkCodecService $typoLinkCodecService;
    private LinkService $linkService;
    private HeadlessFrontendUrlInterface $urlUtility;

    public function __construct(
        TypoLinkCodecService $typoLinkCodecService,
        LinkService $linkService,
        HeadlessFrontendUrlInterface $urlUtility
    ) {
        $this->typoLinkCodecService = $typoLinkCodecService;
        $this->linkService = $linkService;
        $this->urlUtility = $urlUtility;
    }

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

        if (($linkDetails['type'] === LinkService::TYPE_PAGE) &&
            strpos($linkParameterParts['additionalParams'], '[action]=') > 0 &&
            strpos($linkParameterParts['additionalParams'], '[controller]=') > 0) {
            try {
                $site = $request->getAttribute('site');
                parse_str($linkParameterParts['url'], $typolinkData);
                parse_str($linkParameterParts['additionalParams'], $params);

                $languageId = isset($typolinkData['L']) ? (int)$typolinkData['L'] : 0;

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
            } catch (\Exception $exception) {
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
     * @param string $redirectTarget
     * @return array
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
                    if ($file instanceof File) {
                        $linkDetails['url'] = $file->getPublicUrl();
                    }
                    break;
                case LinkService::TYPE_FOLDER:
                    /** @var Folder $folder */
                    $folder = $linkDetails['folder'];
                    if ($folder instanceof Folder) {
                        $linkDetails['url'] = $folder->getPublicUrl();
                    }
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
     *
     * @param Site $site
     * @return PageRouter
     */
    protected function getPageRouterForSite(Site $site): PageRouter
    {
        return GeneralUtility::makeInstance(PageRouter::class, $site);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $message
     * @param array $context
     */
    protected function logError(string $message, array $context): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
