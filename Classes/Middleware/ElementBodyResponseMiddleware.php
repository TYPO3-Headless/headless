<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;

use function in_array;
use function is_array;
use function is_scalar;
use function json_decode;

class ElementBodyResponseMiddleware implements MiddlewareInterface
{
    public function __construct(protected JsonEncoder $jsonEncoder, protected HeadlessModeInterface $headlessMode) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /**
         * @var Site
         */
        $site = $request->getAttribute('site');

        if (!($site instanceof Site)) {
            return $response;
        }

        if (!$this->headlessMode->withRequest($request)->isEnabled()) {
            return $response;
        }

        $parsedBody = $request->getParsedBody();

        if (!is_array($parsedBody)) {
            return $response;
        }

        $rawElementId = $parsedBody['responseElementId'] ?? 0;
        $elementId = is_scalar($rawElementId) ? (int)$rawElementId : 0;

        if (!$elementId || !in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
            return $response;
        }

        $rawRecursive = $parsedBody['responseElementRecursive'] ?? 0;
        $recursiveElement = is_scalar($rawRecursive) && (bool)(int)$rawRecursive;
        $responseJson = json_decode($response->getBody()->__toString(), true);

        if ($responseJson === null) {
            return $response;
        }

        $content = $responseJson['content'] ?? [];

        $stream = new Stream('php://temp', 'r+');
        $stream->write($this->jsonEncoder->encode($this->extractElement(
            is_array($content) ? $content : [],
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
