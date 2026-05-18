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
use TYPO3\CMS\Core\Configuration\Features;

use function json_encode;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

class JsonEncoder implements JsonEncoderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_FLAGS = JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

    private readonly bool $prettyPrint;

    public function __construct(Features $features)
    {
        $this->prettyPrint = $features->isFeatureEnabled('headless.prettyPrint');
    }

    /**
     * @inheritDoc
     */
    public function encode($data, int $options = 0): string
    {
        try {
            $options |= self::DEFAULT_FLAGS;

            if ($this->prettyPrint) {
                $options |= JSON_PRETTY_PRINT;
            }

            return json_encode($data, $options);
        } catch (JsonException $e) {
            $this->logger->critical($e->getMessage());
            return json_encode([], self::DEFAULT_FLAGS);
        }
    }
}
