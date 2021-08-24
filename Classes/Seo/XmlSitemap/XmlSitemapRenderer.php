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

namespace FriendsOfTYPO3\Headless\Seo\XmlSitemap;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Seo\XmlSitemap\Exception\InvalidConfigurationException;

use function trim;

/**
 * Class to render the XML Sitemap to be used as a UserFunction
 * @internal this class is not part of TYPO3's Core API.
 */
class XmlSitemapRenderer extends \TYPO3\CMS\Seo\XmlSitemap\XmlSitemapRenderer
{
    /**
     * @return string
     * @throws InvalidConfigurationException
     */
    public function render(string $_, array $typoScriptConfiguration): string
    {
        $this->prepareBaseUrl();
        return parent::render($_, $typoScriptConfiguration);
    }

    private function prepareBaseUrl(): void
    {
        $conf = $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getConfiguration();
        $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);

        $variantKey = trim($this->configuration['config']['overrideFrontendBaseKey'] ?? 'frontendBase');

        if ($variantKey === '') {
            $variantKey = 'frontendBase';
        }

        $this->view->assign(
            'frontendBase',
            $frontendBase->resolveWithVariants(
                $conf[$variantKey] ?? '',
                $conf['baseVariants'] ?? null,
                $variantKey
            )
        );
    }
}
