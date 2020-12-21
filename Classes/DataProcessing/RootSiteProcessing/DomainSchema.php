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

namespace FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\Service\SiteService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function is_array;
use function str_replace;

class DomainSchema implements SiteSchemaInterface
{
    /**
     * @var SiteService
     */
    private $siteService;
    /**
     * @var ContentDataProcessor
     */
    private $contentDataProcessor;

    public function __construct(SiteService $service = null, ContentDataProcessor $contentObjectRenderer = null)
    {
        $this->siteService = $service ?? GeneralUtility::makeInstance(SiteService::class);
        $this->contentDataProcessor = $contentObjectRenderer ??
            GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

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
            $baseUrl = $protocol . $site->getBase()->getHost();
            $url = $this->siteService->getFrontendUrl($baseUrl, $site->getRootPageId());

            $locales = [];

            foreach ($site->getLanguages() as $language) {
                $locales[] = $language->getTypo3Language();
            }

            $domain = [
                'name' => str_replace($protocol, '', $url),
                'baseURL' => $url,
                'api' => ['baseURL' => $baseUrl],
                'i18n' => [
                    'locales' => $locales,
                    'defaultLocale' => $site->getDefaultLanguage()->getTypo3Language()
                ],
            ];

            // process if necessary
            if (!empty($processorConfiguration['dataProcessing.']) &&
                is_array($processorConfiguration['dataProcessing.'])) {
                $domain = $this->processAdditionalDataProcessors(
                    $domain,
                    $cObj,
                    $processorConfiguration
                );
            }

            $result[] = $domain;
        }

        return $result;
    }

    /**
     * Process additional data processors
     *
     * @param array<string, mixed> $page
     * @param ContentObjectRenderer $cObj
     * @param array<string, mixed> $processorConfiguration
     * @return array<string, mixed>
     */
    protected function processAdditionalDataProcessors(
        array $page,
        ContentObjectRenderer $cObj,
        array $processorConfiguration
    ): array {
        $cObj->start($page, 'pages');
        return $this->contentDataProcessor->process($cObj, $processorConfiguration, $page);
    }
}
