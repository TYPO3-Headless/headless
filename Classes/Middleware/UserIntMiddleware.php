<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UserIntMiddleware implements MiddlewareInterface
{
    private HeadlessUserInt $headlessUserInt;
    private HeadlessMode $headlessMode;

    public function __construct(
        HeadlessUserInt $headlessUserInt = null,
        HeadlessMode $headlessMode
    ) {
        $this->headlessUserInt = $headlessUserInt ?? GeneralUtility::makeInstance(HeadlessUserInt::class);
        $this->headlessMode = $headlessMode;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->headlessMode->withRequest($request)->isEnabled()) {
            return $response;
        }

        $body = $this->headlessUserInt->unwrap($response->getBody()->__toString());

        $stream = new Stream('php://temp', 'r+');
        $stream->write($body);

        return $response->withBody($stream);
    }
}
