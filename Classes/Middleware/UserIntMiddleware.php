<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Json\JsonDecoderInterface;
use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Seo\MetaHandlerInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use TYPO3\CMS\Core\Http\Stream;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class UserIntMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HeadlessUserInt $headlessUserInt,
        private readonly HeadlessModeInterface $headlessMode,
        private readonly MetaHandlerInterface $metaHandler,
        private readonly JsonEncoderInterface $jsonEncoder,
        private readonly JsonDecoderInterface $jsonDecoder,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->headlessMode->isEnabledFor($request)) {
            return $response;
        }

        $jsonContent = $response->getBody()->__toString();

        if (!$this->headlessUserInt->hasNonCacheableContent($jsonContent)) {
            return $response;
        }

        $jsonContent = $this->headlessUserInt->unwrap($jsonContent);

        try {
            $responseBody = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $responseBody = null;
        }

        if (is_array($responseBody) && ($responseBody['seo']['title'] ?? null) !== null) {
            $responseBody = $this->metaHandler->process(
                $request,
                $this->jsonDecoder->decode($responseBody)
            );
            $jsonContent = $this->jsonEncoder->encode($responseBody);
        }

        $stream = new Stream('php://temp', 'r+');
        $stream->write($jsonContent);
        return $response->withBody($stream);
    }
}
