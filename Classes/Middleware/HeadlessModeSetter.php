<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class HeadlessModeSetter implements MiddlewareInterface
{
    public function __construct()
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mode = HeadlessMode::NONE;

        /**
         * @var Site $site
         */
        $site = $request->getAttribute('site');
        if ($site) {
            $mode = (int)($site->getConfiguration()['headless'] ?? HeadlessMode::NONE);
        }

        $request = $request->withAttribute('headless', new Headless($mode));

        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $handler->handle($request);
    }
}
