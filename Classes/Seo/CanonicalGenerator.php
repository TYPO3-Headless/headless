<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo;

use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Seo\Canonical\CanonicalGenerator as CoreCanonicalGenerator;

use function htmlspecialchars;

/**
 * Decorate Core version with headless flavor
 *
 * @codeCoverageIgnore
 */
class CanonicalGenerator
{
    /**
     * @param array<string, mixed> $params
     */
    public function handle(array &$params): string
    {
        $canonical = GeneralUtility::makeInstance(CoreCanonicalGenerator::class)->generate($params);

        if ($canonical === '') {
            return '';
        }

        if ($this->getHeadlessMode()->isEnabledFor($params['request'])) {
            $canonical = [
                'href' => $this->processCanonical($canonical),
                'rel' => 'canonical',
            ];

            $params['_seoLinks'][] = $canonical;
            $canonical = $this->getJsonEncoder()->encode($canonical);
        }

        return $canonical;
    }

    protected function processCanonical(string $canonical): string
    {
        return htmlspecialchars(GeneralUtility::get_tag_attributes($canonical, true)['href'] ?? '');
    }

    protected function getHeadlessMode(): HeadlessModeInterface
    {
        return GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    protected function getJsonEncoder(): JsonEncoderInterface
    {
        return GeneralUtility::makeInstance(JsonEncoderInterface::class);
    }
}
