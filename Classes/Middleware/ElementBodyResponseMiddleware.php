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
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;

use TYPO3\CMS\Core\Site\Entity\Site;

use function in_array;
use function is_array;

use function json_decode;

use const JSON_THROW_ON_ERROR;

class ElementBodyResponseMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected JsonEncoderInterface $jsonEncoder,
        protected HeadlessModeInterface $headlessMode,
        protected JsonDecoderInterface $jsonDecoder,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $site = $request->getAttribute('site');

        if (!($site instanceof Site)) {
            return $response;
        }

        if (!$this->headlessMode->isEnabledFor($request)) {
            return $response;
        }

        $elementId = (int)($request->getParsedBody()['responseElementId'] ?? 0);

        if ($elementId <= 0 || !in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
            return $response;
        }

        $recursiveElement = (bool)(int)($request->getParsedBody()['responseElementRecursive'] ?? 0);

        $body = $response->getBody()->__toString();
        if ($body === '') {
            return $response;
        }

        try {
            $responseJson = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response;
        }

        if (!is_array($responseJson)) {
            return $response;
        }

        $content = $responseJson['content'] ?? [];
        if (is_array($content)) {
            $content = $this->jsonDecoder->decode($content);
        } else {
            $content = [];
        }

        $stream = new Stream('php://temp', 'r+');
        $stream->write($this->jsonEncoder->encode($this->extractElement(
            $content,
            $elementId,
            $recursiveElement
        )));

        return $response->withBody($stream);
    }

    /**
     * @param array<string, mixed> $content
     * @param int $elementId
     * @return array<string, mixed>
     */
    private function extractElement(array $content, int $elementId, bool $recursiveElement = false): array
    {
        $body = [];

        foreach ($content as $items) {
            if (!is_array($items)) {
                continue;
            }
            // if array is flat means doNotGroupByColPos = 1 is set
            if ((int)($items['id'] ?? 0) === $elementId) {
                return $items;
            }

            foreach ($items as $item) {
                if ((int)($item['id'] ?? 0) === $elementId) {
                    return $item;
                }

                if ($recursiveElement && is_array($item)) {
                    foreach ($item as $prop) {
                        if (is_array($prop)) {
                            $result = $this->extractElement($prop, $elementId, true);

                            if (!empty($result)) {
                                return $result;
                            }
                        }
                    }
                }
            }
        }

        return $body;
    }
}
