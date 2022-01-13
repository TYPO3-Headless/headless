<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function is_array;
use function trim;

class SiteSchema implements SiteSchemaInterface
{
    private HeadlessFrontendUrlInterface $urlUtitlity;
    private ContentDataProcessor $contentDataProcessor;

    public function __construct(
        HeadlessFrontendUrlInterface $urlUtitlity = null,
        ContentDataProcessor $contentObjectRenderer = null
    ) {
        $this->urlUtitlity = $urlUtitlity ?? GeneralUtility::makeInstance(UrlUtility::class);
        $this->contentDataProcessor = $contentObjectRenderer ??
            GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function process(SiteProviderInterface $provider, array $options = []): array
    {
        $processorConfiguration = $options['processorConfiguration'] ?? [];
        $siteUid = (int)($options['siteUid'] ?? 0);
        $titleField = $processorConfiguration['titleField'] ?? 'title';

        if (trim($titleField) === '') {
            $titleField = 'title';
        }

        $cObj = $options['cObj'] ?? GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $result = [];
        $pages = $provider->getPages();

        foreach ($provider->getSites() as $site) {
            $current = 0;
            $active = 0;
            $spacer = 0;
            $baseUrl = $site->getBase()->getScheme() . '://' . $site->getBase()->getHost();
            $url = $this->urlUtitlity->getFrontendUrlForPage($baseUrl, $site->getRootPageId());

            if ($provider->getCurrentRootPage() === $site) {
                $active = 1;
                if ($site->getRootPageId() === $siteUid) {
                    $current = 1;
                }
            }

            $pageInfo = $pages[$site->getRootPageId()];

            $page = [
                'title' => $pageInfo[$titleField],
                'link' => $url,
                'active' => $active,
                'current' => $current,
                'spacer' => $spacer,
            ];

            // process page if necessary
            if (isset($processorConfiguration['dataProcessing.']) &&
                is_array($processorConfiguration['dataProcessing.'])) {
                $page = $this->processAdditionalDataProcessors(
                    $page,
                    $cObj,
                    $processorConfiguration
                );
            }

            $result[] = $page;
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
        $page,
        ContentObjectRenderer $cObj,
        $processorConfiguration
    ): array {
        $cObj->start($page, 'pages');
        return $this->contentDataProcessor->process($cObj, $processorConfiguration, $page);
    }
}
