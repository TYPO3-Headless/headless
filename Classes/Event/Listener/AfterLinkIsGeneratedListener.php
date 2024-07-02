<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

use function is_numeric;
use function is_string;
use function str_starts_with;

final class AfterLinkIsGeneratedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly LinkService $linkService,
        private readonly TypoLinkCodecService $typoLinkCodecService,
        private readonly SiteFinder $siteFinder
    ) {}

    public function __invoke(AfterLinkIsGeneratedEvent $event): void
    {
        $result = $event->getLinkResult();

        if ($result->getType() !== 'page') {
            return;
        }

        $pageId = $result->getLinkConfiguration()['parameter'] ?? 0;

        if (isset($result->getLinkConfiguration()['parameter.'])) {
            $pageId = (int)($this->linkService->resolve($event->getContentObjectRenderer()->parameters['href'] ?? '')['pageuid'] ?? 0);
        }

        $urlUtility = $this->urlUtility->withRequest($event->getContentObjectRenderer()->getRequest());

        if (is_numeric($pageId) && ((int)$pageId) > 0) {
            $href = $urlUtility->getFrontendUrlForPage(
                $event->getLinkResult()->getUrl(),
                (int)$pageId
            );
        } else {
            try {
                $site = $this->getTargetSite($event);
                $key = 'frontendBase';

                if (is_string($pageId) && str_starts_with($pageId, 't3://page?uid=current&type=' . $site->getSettings()->get('headless.sitemap.type', '1533906435'))) {
                    $key = $site->getSettings()->get('headless.sitemap.key', 'frontendApiProxy');
                }

                $href = $urlUtility->getFrontendUrlWithSite($event->getLinkResult()->getUrl(), $site, $key);
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }

        if (isset($href)) {
            $result = $result->withAttribute('href', $href);
            $event->setLinkResult($result);
        }
    }

    private function getTargetSite(AfterLinkIsGeneratedEvent $event): Site
    {
        $linkConfiguration = $event->getLinkResult()->getLinkConfiguration();

        if (isset($linkConfiguration['parameter.'])) {
            // Evaluate "parameter." stdWrap but keep additional information (like target, class and title)
            $linkParameterParts = $this->typoLinkCodecService->decode($linkConfiguration['parameter'] ?? '');
            $modifiedLinkParameterString = $event->getContentObjectRenderer()->stdWrap($linkParameterParts['url'], $linkConfiguration['parameter.']);
            // As the stdWrap result might contain target etc. as well again (".field = header_link")
            // the result is then taken from the stdWrap and overridden if the value is not empty.
            $modifiedLinkParameterParts = $this->typoLinkCodecService->decode((string)($modifiedLinkParameterString ?? ''));
            $linkParameterParts = array_replace($linkParameterParts, array_filter($modifiedLinkParameterParts, static fn($value) => trim((string)$value) !== ''));
            $linkParameter = $this->typoLinkCodecService->encode($linkParameterParts);
        } else {
            $linkParameter = trim((string)($linkConfiguration['parameter'] ?? ''));
        }

        try {
            [$linkParameter] = $this->resolveTypolinkParameterString($linkParameter, $linkConfiguration);
        } catch (UnableToLinkException $e) {
            $this->logger->warning($e->getMessage(), ['linkConfiguration' => $linkConfiguration]);
            throw $e;
        }
        $linkDetails = $this->resolveLinkDetails($linkParameter, $linkConfiguration, $event->getContentObjectRenderer());
        if ($linkDetails === null) {
            throw new UnableToLinkException('Could not resolve link details from ' . $linkParameter, 1642001442, null, $event->getLinkResult()->getLinkText());
        }

        if (($linkDetails['pageuid'] ?? '') === 'current') {
            return $event->getContentObjectRenderer()->getRequest()->getAttribute('site');
        }

        return $this->siteFinder->getSiteByPageId((int)$linkDetails['pageuid']);
    }

    protected function resolveLinkDetails(string $linkParameter, array $linkConfiguration, ContentObjectRenderer $contentObjectRenderer): ?array
    {
        $linkDetails = null;
        if (!$linkParameter) {
            // Support anchors without href value if id or name attribute is present.
            $aTagParams = (string)$contentObjectRenderer->stdWrapValue('ATagParams', $linkConfiguration);
            $aTagParams = GeneralUtility::get_tag_attributes($aTagParams);
            // If it looks like an anchor tag, render it anyway
            if (isset($aTagParams['id']) || isset($aTagParams['name'])) {
                $linkDetails = [
                    'type' => LinkService::TYPE_INPAGE,
                    'url' => '',
                ];
            }
        } else {
            // Detecting kind of link and resolve all necessary parameters
            try {
                $linkDetails = $this->linkService->resolve($linkParameter);
            } catch (UnknownLinkHandlerException|InvalidPathException $exception) {
                $this->logger->warning('The link could not be generated', ['exception' => $exception]);
                return null;
            }
        }
        if (is_array($linkDetails)) {
            $linkDetails['typoLinkParameter'] = $linkParameter;
        }
        return $linkDetails;
    }

    private function resolveTypolinkParameterString(string $mixedLinkParameter, array &$linkConfiguration = []): array
    {
        $linkParameterParts = $this->typoLinkCodecService->decode($mixedLinkParameter);
        [$linkHandlerKeyword] = explode(':', $linkParameterParts['url'] ?? '', 2);
        if (in_array(strtolower((string)preg_replace('#\s|[[:cntrl:]]#', '', (string)$linkHandlerKeyword)), ['javascript', 'data'], true)) {
            // Disallow insecure scheme's like javascript: or data:
            throw new UnableToLinkException('Insuecure scheme for linking detected with "' . $mixedLinkParameter . "'", 1641986533);
        }

        // additional parameters that need to be set
        if (($linkParameterParts['additionalParams'] ?? '') !== '') {
            $forceParams = $linkParameterParts['additionalParams'];
            // params value
            $linkConfiguration['additionalParams'] = ($linkConfiguration['additionalParams'] ?? '') . $forceParams[0] === '&' ? $forceParams : '&' . $forceParams;
        }

        return [
            $linkParameterParts['url'] ?? '',
            $linkParameterParts['target'] ?? '',
            $linkParameterParts['class'] ?? '',
            $linkParameterParts['title'] ?? '',
        ];
    }
}
