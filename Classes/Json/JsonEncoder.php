<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Json;

use JsonException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use function json_encode;

use const JSON_THROW_ON_ERROR;

class JsonEncoder implements JsonEncoderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @inheritDoc
     */
    public function encode($data, int $options = 0): string
    {
        try {
            if (!($options & JSON_THROW_ON_ERROR)) {
                $options |= JSON_THROW_ON_ERROR;
            }

            return json_encode($data, $options);
        } catch (JsonException $e) {
            $this->logger->critical($e->getMessage());
            return json_encode([]);
        }
    }
}
