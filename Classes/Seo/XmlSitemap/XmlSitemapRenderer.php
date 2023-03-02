<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo\XmlSitemap;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3Fluid\Fluid\View\TemplateView;
use function is_array;
use function parse_url;
use function trim;

/**
 * Class to render the XML Sitemap to be used as a UserFunction
 *
 * TODO is to check if we can get rid of this modification, eg. by changing behaviour of typoLink_URL globaly
 *
 * @codeCoverageIgnore
 */
class XmlSitemapRenderer extends CoreXmlSitemapRenderer
{
    protected function renderSitemap(ServerRequestInterface $request, TemplateView $view, array $configConfiguration, string $sitemapType, string $sitemapName): string
    {
        $this->prepareBaseUrl($view, $configConfiguration);
        return parent::renderSitemap($request, $view, $configConfiguration, $sitemapType, $sitemapName);
    }

    protected function renderIndex(ServerRequestInterface $request, TemplateView $view, array $configConfiguration, string $sitemapType): string
    {
        $this->prepareBaseUrl($view, $configConfiguration);
        return parent::renderIndex($request, $view, $configConfiguration, $sitemapType);
    }

    /**
     * @param string|null $sitemapType
     * @param string|null $sitemap
     * @return string
     */
    protected function getXslFilePath(array $configConfiguration, string $sitemapType, string $sitemapName = null): string
    {
        $path = parent::getXslFilePath($configConfiguration, $sitemapType, $sitemapName);
        $parsed = parse_url($this->getVariantValueByKey('frontendApiProxy'));

        if (is_array($parsed)) {
            $path = ($parsed['path'] ?? '') . $path;
        }

        return $path;
    }

    private function getVariantValueByKey(string $variantKey): string
    {
        return GeneralUtility::makeInstance(UrlUtility::class)->resolveKey($variantKey);
    }

    private function prepareBaseUrl(TemplateView $view, array $configuration): void
    {
        $variantKey = trim($configuration['config']['overrideVariantKey'] ?? 'frontendBase');

        if ($variantKey === '') {
            $variantKey = 'frontendBase';
        }

        $view->assign('frontendBase', $this->getVariantValueByKey($variantKey));
    }
}
