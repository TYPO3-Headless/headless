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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function is_array;
use function parse_url;
use function trim;

/**
 * Class to render the XML Sitemap to be used as a UserFunction
 * @internal this class is not part of TYPO3's Core API.
 */
class XmlSitemapRenderer extends \TYPO3\CMS\Seo\XmlSitemap\XmlSitemapRenderer
{
    /**
     * @inheritDoc
     */
    public function render(string $_, array $typoScriptConfiguration, ServerRequestInterface $request): string
    {
        $this->prepareBaseUrl();
        return parent::render($_, $typoScriptConfiguration, $request);
    }

    /**
     * @param string|null $sitemapType
     * @param string|null $sitemap
     * @return string
     */
    protected function getXslFilePath(string $sitemapType = null, string $sitemap = null): string
    {
        $path = parent::getXslFilePath($sitemapType, $sitemap);
        $parsed = parse_url($this->getVariantValueByKey('frontendApiProxy'));

        if (is_array($parsed)) {
            $path = ($parsed['path'] ?? '') . $path;
        }

        return $path;
    }

    private function getVariantValueByKey(string $variantKey): string
    {
        $conf = $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getConfiguration();
        $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);

        return $frontendBase->resolveWithVariants(
            $conf[$variantKey] ?? '',
            $conf['baseVariants'] ?? null,
            $variantKey
        );
    }

    private function prepareBaseUrl(): void
    {
        $variantKey = trim($this->configuration['config']['overrideVariantKey'] ?? 'frontendBase');

        if ($variantKey === '') {
            $variantKey = 'frontendBase';
        }

        $this->view->assign('frontendBase', $this->getVariantValueByKey($variantKey));
    }
}
