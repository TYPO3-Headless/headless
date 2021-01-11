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

use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

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
        'message' => null,
    ];

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Request
     */
    protected $request;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Response
     */
    protected $response;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder
     */
    protected $uriBuilder;

    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     */
    protected function executeInternal(): ?string
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $this->request = $formRuntime->getRequest();
        $this->response = $formRuntime->getResponse();
        $this->uriBuilder = $this->objectManager->get(UriBuilder::class);
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

        /**
         * @var string|null
         */
        $message = $this->parseOption('message');
        $additionalParameters = $this->parseOption('additionalParameters');
        $additionalParameters = is_string($additionalParameters) ? $additionalParameters : '';
        $additionalParameters = '&' . ltrim($additionalParameters, '&');

        $this->finisherContext->cancel();

        return $this->prepareRedirect($pageUid, $additionalParameters, $message);
    }

    /**
     * @param int $pageUid Target page uid. If NULL, the current page uid is used
     * @param string $additionalParameters
     * @param string|null $message (optional)
     * @return string|null
     */
    protected function prepareRedirect(
        int $pageUid = 1,
        string $additionalParameters = '',
        ?string $message = null
    ): ?string {
        $typolinkConfiguration = [
            'parameter' => $pageUid,
            'additionalParams' => $additionalParameters,
        ];
        /**
         * @phpstan-ignore-next-line
         */
        $redirectUri = $this->getTypoScriptFrontendController()->cObj->typoLink_URL($typolinkConfiguration);

        try {
            return \json_encode(
                [
                    'message' => $message,
                    'redirectUri' => $redirectUri,
                ],
                \PHP_VERSION_ID >= 70300 ? \JSON_THROW_ON_ERROR : 0
            );
        } catch (\JsonException $e) {
            return null;
        }
    }
}
