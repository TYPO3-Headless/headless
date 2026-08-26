<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Finisher;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use JsonException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function is_string;
use function json_encode;
use function ltrim;

use const JSON_THROW_ON_ERROR;

/**
 * This finisher redirects to another Controller.
 *
 * Scope: frontend
 */
class JsonRedirectFinisher extends AbstractFinisher
{
    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'pageUid' => 1,
        'additionalParameters' => '',
        'statusCode' => 303,
        'message' => null,
        'sameSiteOnly' => false,
    ];

    protected RequestInterface $request;
    protected UriBuilder $uriBuilder;

    protected ?HeadlessFrontendUrlInterface $urlUtility = null;

    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     */
    protected function executeInternal(): ?string
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $this->request = $formRuntime->getRequest();
        $this->uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $this->uriBuilder->setRequest($this->request);

        /**
         * @var string|int|null
         */
        $pageUid = $this->parseOption('pageUid');

        if (is_string($pageUid)) {
            $pageUid = (int)str_replace('pages_', '', $pageUid);
        } else {
            $pageUid = (int)$pageUid;
        }

        /** @var int|string|null $statusCodeOption */
        $statusCodeOption = $this->parseOption('statusCode');
        $statusCode = (int)$statusCodeOption;

        /**
         * @var string|null
         */
        $message = $this->parseOption('message');
        $additionalParameters = $this->parseOption('additionalParameters');
        $additionalParameters = is_string($additionalParameters) ? $additionalParameters : '';
        $additionalParameters = '&' . ltrim($additionalParameters, '&');

        $sameSiteOnly = (bool)$this->parseOption('sameSiteOnly');

        $this->finisherContext->cancel();

        return $this->prepareRedirect($pageUid, $additionalParameters, $statusCode, $message, $sameSiteOnly);
    }

    protected function prepareRedirect(
        int $pageUid = 1,
        string $additionalParameters = '',
        int $statusCode = 303,
        ?string $message = null,
        bool $sameSiteOnly = false
    ): ?string {
        try {
            $serverRequest = $this->request->getAttribute('extbase.request.originalRequest')
                ?? $GLOBALS['TYPO3_REQUEST'];

            if ($sameSiteOnly) {
                $pageUid = $this->restrictToCurrentSite($pageUid, $serverRequest?->getAttribute('site'));
            }

            $urlUtility = ($this->urlUtility ??= GeneralUtility::makeInstance(HeadlessFrontendUrlInterface::class))->withRequest($serverRequest);

            $cObj = $serverRequest->getAttribute('currentContentObject');
            if ($cObj === null) {
                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $cObj->setRequest($serverRequest);
                $pageInformation = $serverRequest->getAttribute('frontend.page.information');
                $cObj->start($pageInformation?->getPageRecord() ?? [], 'pages');
            }

            $targetUrl = $cObj->typoLink_URL([
                'parameter' => $pageUid,
                'additionalParams' => $additionalParameters,
                'forceAbsoluteUrl' => 1,
            ]);

            return json_encode(
                [
                    'redirectUrl' => $urlUtility->prepareRelativeUrlIfPossible($targetUrl),
                    'statusCode' => $statusCode,
                    'message' => $message,
                ],
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            $this->logger?->warning('JsonRedirectFinisher: JSON encode failed', ['exception' => $e]);
            return null;
        }
    }

    protected function restrictToCurrentSite(int $pageUid, mixed $currentSite): int
    {
        if (!$currentSite instanceof Site || $pageUid <= 0) {
            return $pageUid;
        }
        try {
            $targetSite = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageUid);
        } catch (SiteNotFoundException) {
            $this->logger?->warning('JsonRedirectFinisher: pageUid not found, falling back', ['pageUid' => $pageUid]);
            return (int)($this->defaultOptions['pageUid'] ?? 1);
        }
        if ($targetSite->getIdentifier() !== $currentSite->getIdentifier()) {
            $this->logger?->warning('JsonRedirectFinisher: cross-site redirect blocked', [
                'pageUid' => $pageUid,
                'currentSite' => $currentSite->getIdentifier(),
                'targetSite' => $targetSite->getIdentifier(),
            ]);
            return (int)($this->defaultOptions['pageUid'] ?? 1);
        }
        return $pageUid;
    }
}
