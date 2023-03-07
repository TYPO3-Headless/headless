<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;

use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

use function is_array;
use function parse_url;

class ShortcutAndMountPointRedirect implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $pageType = (int)($queryParams['type'] ?? 0);

        if ($pageType === 834) {
            return $handler->handle($request);
        }

        $exposeInformation = $GLOBALS['TYPO3_CONF_VARS']['FE']['exposeRedirectInformation'] ?? false;

        // Check for shortcut page and mount point redirect
        try {
            $redirectToUri = $this->getRedirectUri($request);
        } catch (ImmediateResponseException $e) {
            return $e->getResponse();
        }
        if ($redirectToUri !== null && $redirectToUri !== (string)$request->getUri()) {
            /** @var PageArguments $pageArguments */
            $pageArguments = $request->getAttribute('routing', null);
            $message = 'TYPO3 Shortcut/Mountpoint' . ($exposeInformation ? ' at page with ID ' . $pageArguments->getPageId() : '');

            if ($this->isHeadlessEnabled($request)) {
                $parsed = parse_url($redirectToUri);
                if (is_array($parsed)) {
                    $path = $parsed['path'] ?? '/';
                    return new JsonResponse(['redirectUrl' => $path, 'statusCode' => 307]);
                }
            }

            return new RedirectResponse(
                $redirectToUri,
                307,
                ['X-Redirect-By' => $message]
            );
        }

        // See if the current page is of doktype "External URL", if so, do a redirect as well.
        $controller = $request->getAttribute('frontend.controller');
        if ((int)$controller->page['doktype'] === PageRepository::DOKTYPE_LINK) {
            $externalUrl = $this->prefixExternalPageUrl(
                $controller->page['url'],
                $request->getAttribute('normalizedParams')->getSiteUrl()
            );
            $message = 'TYPO3 External URL' . ($exposeInformation ? ' at page with ID ' . $controller->page['uid'] : '');
            if (!empty($externalUrl)) {
                if ($this->isHeadlessEnabled($request)) {
                    return new JsonResponse(['redirectUrl' => $externalUrl, 'statusCode' => 303]);
                }

                return new RedirectResponse(
                    $externalUrl,
                    303,
                    ['X-Redirect-By' => $message]
                );
            }
            $this->logger->error(
                'Page of type "External URL" could not be resolved properly',
                [
                    'page' => $controller->page,
                ]
            );
            return GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $request,
                'Page of type "External URL" could not be resolved properly',
                $controller->getPageAccessFailureReasons(PageAccessFailureReasons::INVALID_EXTERNAL_URL)
            );
        }

        return $handler->handle($request);
    }

    protected function getRedirectUri(ServerRequestInterface $request): ?string
    {
        $controller = $request->getAttribute('frontend.controller');
        $redirectToUri = $controller->getRedirectUriForShortcut($request);
        return $redirectToUri ?? $controller->getRedirectUriForMountPoint($request);
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
        // If relative path, prefix Site URL
        // If it's a valid email without protocol, add "mailto:"
        if (!($uI['scheme'] ?? false)) {
            if (GeneralUtility::validEmail($redirectTo)) {
                $redirectTo = 'mailto:' . $redirectTo;
            } elseif (!str_starts_with($redirectTo, '/')) {
                $redirectTo = $sitePrefix . $redirectTo;
            }
        }
        return $redirectTo;
    }

    private function isHeadlessEnabled(ServerRequestInterface $request): bool
    {
        /**
         * @var Site
         */
        $site = $request->getAttribute('site');

        if (!($site instanceof Site)) {
            return false;
        }

        $siteConf = $request->getAttribute('site')->getConfiguration();

        return $siteConf['headless'] ?? false;
    }
}
