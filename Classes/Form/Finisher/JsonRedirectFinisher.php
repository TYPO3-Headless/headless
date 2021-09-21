<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Finisher;

use FriendsOfTYPO3\Headless\Service\SiteService;
use JsonException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use function is_array;
use function is_string;
use function json_encode;
use function ltrim;
use function parse_url;
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
    ];

    protected Request $request;
    protected UriBuilder $uriBuilder;

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

        $statusCode = (int)$this->parseOption('statusCode');

        /**
         * @var string|null
         */
        $message = $this->parseOption('message');
        $additionalParameters = $this->parseOption('additionalParameters');
        $additionalParameters = is_string($additionalParameters) ? $additionalParameters : '';
        $additionalParameters = '&' . ltrim($additionalParameters, '&');

        $this->finisherContext->cancel();

        return $this->prepareRedirect($pageUid, $additionalParameters, $statusCode, $message);
    }

    protected function prepareRedirect(
        int $pageUid = 1,
        string $additionalParameters = '',
        int $statusCode = 303,
        ?string $message = null
    ): ?string {
        try {
            $targetUrl = $this->getTypoScriptFrontendController()->cObj->typoLink_URL([
                'parameter' => $pageUid,
                'additionalParams' => $additionalParameters,
                'forceAbsoluteUrl' => 1,
            ]);

            $site = $this->request->getServerRequest()->getAttribute('site');
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $requestDomainUrl = $siteService->getFrontendUrl((string)$this->request->getServerRequest()->getUri(), $site->getRootPageId());
            $parsedTargetUrl = parse_url($targetUrl);
            $parsedDomainUrl = parse_url($requestDomainUrl);

            if (is_array($parsedTargetUrl) &&
                is_array($parsedDomainUrl) &&
                ($parsedTargetUrl['host'] ?? '') === ($parsedDomainUrl['host'] ?? '')) {
                $targetUrl = $parsedTargetUrl['path'] ?? '';
            }

            return json_encode(
                [
                    'redirectUrl' => $targetUrl,
                    'statusCode' => $statusCode,
                    'message' => $message,
                ],
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            return null;
        }
    }
}
