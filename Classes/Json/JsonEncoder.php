<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Json;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backported from Symfony Serializer
 * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Serializer/Encoder/JsonEncode.php
 */
final class JsonEncoder implements JsonEncoderInterface
{
    /**
     * @var JsonDecoderInterface
     */
    private $jsonDecoder;

    /**
     * @param JsonDecoderInterface $jsonDecoder
     */
    public function __construct(JsonDecoderInterface $jsonDecoder = null)
    {
        $this->jsonDecoder = $jsonDecoder ?? GeneralUtility::makeInstance(JsonDecoder::class);
    }

    /**
     * @param array $data
     * @param int $encodeOptions
     * @throws JsonEncoderException
     * @return string
     */
    public function encode(array $data, int $encodeOptions = 0): string
    {
        try {
            $encodedJson = json_encode($this->jsonDecoder->decode($data), $encodeOptions);
        } catch (\JsonException $e) {
            throw new JsonEncoderException($e->getMessage(), $e->getCode(), $e);
        }

        if (\PHP_VERSION_ID >= 70300 && (\JSON_THROW_ON_ERROR & $encodeOptions)) {
            return $encodedJson;
        }

        if (json_last_error() !== JSON_ERROR_NONE && ($encodedJson === false || !($encodeOptions & \JSON_PARTIAL_OUTPUT_ON_ERROR))) {
            throw new JsonEncoderException(json_last_error_msg(), json_last_error());
        }

        return $encodedJson;
    }
}
