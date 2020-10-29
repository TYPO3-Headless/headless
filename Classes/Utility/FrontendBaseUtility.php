<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FrontendBaseUtility
{
    /**
     * @param string $frontendUrl
     * @param array|null $baseVariants
     * @return string
     */
    public function resolveWithVariants(string $frontendUrl, ?array $baseVariants): string
    {
        if (empty($baseVariants)) {
            return $frontendUrl;
        }

        $expressionLanguageResolver = GeneralUtility::makeInstance(
            Resolver::class,
            'site',
            []
        );

        foreach ($baseVariants as $baseVariant) {
            try {
                if ($expressionLanguageResolver->evaluate($baseVariant['condition'])) {
                    return $baseVariant['frontendBase'];
                }
            } catch (SyntaxError $e) {
                // silently fail and do not evaluate
                // no logger here, as Site is currently cached and serialized
            }
        }

        return $frontendUrl;
    }
}
