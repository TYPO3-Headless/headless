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

namespace FriendsOfTYPO3\Headless\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use function is_array;
use function parse_url;

class ShortcutAndMountPointRedirect implements MiddlewareInterface
{
    /**
     * @var TypoScriptFrontendController
     */
    private $controller;

    public function __construct(TypoScriptFrontendController $controller = null)
    {
        $this->controller = $controller ?: $GLOBALS['TSFE'];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirectToUri = $this->getRedirectUri($request);
        if ($redirectToUri !== null && $redirectToUri !== (string)$request->getUri()) {
            $this->releaseTypoScriptFrontendControllerLocks();

            if ($this->isHeadlessEnabled()) {
                $parsed = parse_url($redirectToUri);
                if (is_array($parsed)) {
                    $path = $parsed['path'] ?? '/';
                    return new JsonResponse(['redirectUrl' => $path, 'statusCode' => 307]);
                }
            }

            return new RedirectResponse($redirectToUri, 307);
        }

        // See if the current page is of doktype "External URL", if so, do a redirect as well.
        if (
            empty($this->controller->config['config']['disablePageExternalUrl'] ?? null)
            && PageRepository::DOKTYPE_LINK === (int)$this->controller->page['doktype']
        ) {
            $externalUrl = $this->prefixExternalPageUrl(
                $this->controller->page['url'],
                $request->getAttribute('normalizedParams')->getSiteUrl()
            );

            if ($externalUrl !== '') {
                $this->releaseTypoScriptFrontendControllerLocks();
                if ($this->isHeadlessEnabled()) {
                    return new JsonResponse(['redirectUrl' => $externalUrl, 'statusCode' => 303]);
                }

                return new RedirectResponse($externalUrl, 303);
            }
        }

        return $handler->handle($request);
    }

    protected function getRedirectUri(ServerRequestInterface $request): ?string
    {
        $redirectToUri = $this->controller->getRedirectUriForShortcut($request);
        return $redirectToUri ?? $this->controller->getRedirectUriForMountPoint($request);
    }

    protected function releaseTypoScriptFrontendControllerLocks(): void
    {
        $this->controller->releaseLocks();
    }

    /**
     * Returns the redirect URL for the input page row IF the doktype is set to 3.
     *
     * @param string $redirectTo The page row to return URL type for
     * @param string $sitePrefix if no protocol or relative path given, the site prefix is added
     * @return string The URL from based on the external page URL given with a prefix.
     */
    protected function prefixExternalPageUrl(string $redirectTo, string $sitePrefix): string
    {
        $uI = parse_url($redirectTo);

        if (!$uI) {
            return $redirectTo;
        }

        // If relative path, prefix Site URL
        // If it's a valid email without protocol, add "mailto:"
        if (!($uI['scheme'] ?? false)) {
            if (GeneralUtility::validEmail($redirectTo)) {
                $redirectTo = 'mailto:' . $redirectTo;
            } elseif ($redirectTo[0] !== '/') {
                $redirectTo = $sitePrefix . $redirectTo;
            }
        }
        return $redirectTo;
    }

    private function isHeadlessEnabled(): bool
    {
        $setup = $this->controller->tmpl->setup;

        return !(!isset($setup['plugin.']['tx_headless.']['staticTemplate'])
            || (bool)$setup['plugin.']['tx_headless.']['staticTemplate'] === false);
    }
}
