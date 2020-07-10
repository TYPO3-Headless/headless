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
            $this->logger->critical('Error while encoding json', ['code' => $e->getCode(), 'message' => $e->getMessage()]);
            return '';
        }

        if (\PHP_VERSION_ID >= 70300 && (JSON_THROW_ON_ERROR & $encodeOptions)) {
            return $encodedJson;
        }

        if (json_last_error() !== JSON_ERROR_NONE && ($encodedJson === false || !($encodeOptions & JSON_PARTIAL_OUTPUT_ON_ERROR))) {
            $this->logger->critical('Error while encoding json', ['code' => json_last_error(), 'message' => json_last_error_msg()]);
            return '';
        }

        return $encodedJson;
    }
}
