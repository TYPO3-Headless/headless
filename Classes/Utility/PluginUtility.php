<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;

class PluginUtility
{
    public function __construct(private readonly UrlUtility $urlUtility) {}

    public function redirect(ServerRequestInterface $request, string $uri, int $statusCode = 307): never
    {
        throw new PropagateResponseException(new JsonResponse([
            'redirectUrl' => $this->urlUtility->withRequest($request)->prepareRelativeUrlIfPossible($uri),
            'statusCode' => $statusCode,
        ]));
    }
}
