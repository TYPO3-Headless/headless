<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function str_replace;

class TestDomainSchema implements SiteSchemaInterface
{
    /**
     * @param SiteProviderInterface $provider
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function process(SiteProviderInterface $provider, array $options = []): array
    {
        $processorConfiguration = $options['processorConfiguration'] ?? [];
        $cObj = $options['cObj'] ?? GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $result = [];

        foreach ($provider->getSites() as $site) {
            $protocol = $site->getBase()->getScheme() . '://';
            $url = 'https://frontend.tld';

            $locales = [];

            foreach ($site->getLanguages() as $language) {
                $locales[] = $language->getTypo3Language();
            }

            $domain = [
                'name' => str_replace($protocol, '', $url),
                'baseURL' => $url,
                'api' => [
                    'baseURL' => '/proxy/',
                ],
                'i18n' => [
                    'locales' => $locales,
                    'defaultLocale' => $site->getDefaultLanguage()->getTypo3Language(),
                ],
            ];

            $result[] = $domain;
        }

        return $result;
    }
}
