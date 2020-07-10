<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

namespace FriendsOfTYPO3\Headless\Json;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class JsonEncoder implements JsonEncoderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var JsonDecoderInterface
     */
    private $jsonDecoder;

    /**
     * @param JsonDecoderInterface $jsonDecoder
     */
    public function __construct(JsonDecoderInterface $jsonDecoder)
    {
        $this->jsonDecoder = $jsonDecoder;
    }

    /**
     * @param array $data
     * @param int $encodeOptions
     * @return string
     */
    public function encode(array $data, int $encodeOptions = 0): string
    {
        try {
            $encodedJson = json_encode($this->jsonDecoder->decode($data), $encodeOptions);
        } catch (\JsonException $e) {
            $this->logger->emergency('Error while encoding json', ['error' => $e->getMessage()]);
            return '';
        }

        if (\PHP_VERSION_ID >= 70300 && (JSON_THROW_ON_ERROR & $options)) {
            return $encodedJson;
        }

        if (JSON_ERROR_NONE !== json_last_error() && (false === $encodedJson || !($encodeOptions & JSON_PARTIAL_OUTPUT_ON_ERROR))) {
            $this->logger->emergency('Error while encoding json', ['error' => json_last_error()]);
            return '';
        }

        return $encodedJson;
    }
}
