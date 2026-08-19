<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\Stream;

use function htmlspecialchars;
use function htmlspecialchars_decode;
use function in_array;
use function preg_replace_callback;

/**
 * Rewrites redirect source URLs in the "QR Codes" and "Short URLs" backend
 * modules to the site's frontendBase, so scanned/copied links land on the
 * public frontend instead of the TYPO3 API host.
 */
class RedirectModuleSourceUrlRewriter implements MiddlewareInterface
{
    protected const MODULE_IDENTIFIERS = ['qrcodes', 'short_urls'];

    public function __construct(protected readonly SourceUrlResolver $sourceUrlResolver) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->isRedirectModuleRequest($request)) {
            return $response;
        }

        $html = (string)$response->getBody();

        if ($html === '') {
            return $response;
        }

        $rewritten = preg_replace_callback(
            '/(?<=\s)(content|text)="https?:\/\/([^\/"]+)([^"]*)"/',
            function (array $matches) use ($request): string {
                $resolved = $this->sourceUrlResolver->resolve(
                    $matches[2],
                    htmlspecialchars_decode($matches[3], ENT_QUOTES | ENT_HTML5),
                    $request
                );

                if ($resolved === null) {
                    return $matches[0];
                }

                return $matches[1] . '="' . htmlspecialchars($resolved, ENT_QUOTES | ENT_HTML5) . '"';
            },
            $html
        );

        if ($rewritten === null || $rewritten === $html) {
            return $response;
        }

        $body = new Stream('php://temp', 'rw');
        $body->write($rewritten);

        return $response->withBody($body)->withoutHeader('Content-Length');
    }

    protected function isRedirectModuleRequest(ServerRequestInterface $request): bool
    {
        $route = $request->getAttribute('route');

        if (!$route instanceof Route) {
            return false;
        }

        $module = $route->getOption('module');

        return $module instanceof ModuleInterface
            && in_array($module->getIdentifier(), self::MODULE_IDENTIFIERS, true);
    }
}
