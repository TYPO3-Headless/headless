<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo;

use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;

use function array_merge;
use function array_merge_recursive;
use function htmlspecialchars;
use function implode;

class MetaHandler implements MetaHandlerInterface
{
    public function __construct(
        private readonly MetaTagManagerRegistry $metaTagRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PageTitleProviderManager $pageTitleProviderManager,
    ) {}

    public function process(
        ServerRequestInterface $request,
        array $content
    ): array {
        $pageInformation = $request->getAttribute('frontend.page.information');
        $page = $pageInformation->getPageRecord();

        $_params = ['page' => $page, 'request' => $request, '_seoLinks' => []];
        $_ref = null;
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags'] ?? [] as $_funcRef) {
            GeneralUtility::callUserFunction($_funcRef, $_params, $_ref);
        }

        $typoScriptSetup = $request->getAttribute('frontend.typoscript')->getSetupArray();
        $typoScriptConfig = $typoScriptSetup['config.'] ?? [];

        $content['seo']['title'] = $this->generatePageTitle($request, $typoScriptConfig);

        $cObj = $this->createContentObjectRenderer($request, $page);
        $this->generateMetaTagsFromTyposcript(
            $typoScriptSetup['page.']['meta.'] ?? [],
            $cObj
        );

        $metaTags = [];
        $metaTagManagers = $this->metaTagRegistry->getAllManagers();

        foreach ($metaTagManagers as $managerObject) {
            $properties = json_decode($managerObject->renderAllProperties(), true);
            if (!empty($properties)) {
                $metaTags = array_merge($metaTags, $properties);
            }
        }

        $content['seo']['meta'] = $metaTags;

        $hrefLangs = $this->eventDispatcher->dispatch(new ModifyHrefLangTagsEvent($request))->getHrefLangs();

        $seoLinks = $_params['_seoLinks'] ?? [];

        if (count($hrefLangs) > 1) {
            foreach ($hrefLangs as $hrefLang => $href) {
                $seoLinks[] = ['rel' => 'alternate', 'hreflang' => $hrefLang, 'href' => $href];
            }
        }

        if ($seoLinks !== []) {
            $content['seo']['link'] = $seoLinks;
        }

        /**
         * @var SiteLanguage $language
         */
        $language = $request->getAttribute('language');

        $rawHtmlTagAttrs = $typoScriptConfig['htmlTag.']['attributes.'] ?? [];
        $overwriteBodyTag = (int)($typoScriptConfig['headless.']['overwriteBodyTag'] ?? 0);
        $htmlTagAttrs = $this->normalizeAttr($rawHtmlTagAttrs);

        $defaultBodyAttrs = [
            'class' => implode(' ', [
                'pid-' . $request->getAttribute('routing')->getPageId(),
                'layout-' . ($content['appearance']['layout'] ?? ''),
            ]),
        ];

        $rawBodyTagAttrs = GeneralUtility::get_tag_attributes(trim($typoScriptSetup['page.']['bodyTagAdd'] ?? ''));

        if ($overwriteBodyTag) {
            $bodyTagAttrs = array_merge($defaultBodyAttrs, $rawBodyTagAttrs);
        } else {
            $bodyTagAttrs = array_map(static function (string|array $attr) {
                if (is_array($attr)) {
                    return implode(' ', $attr);
                }

                return $attr;
            }, array_merge_recursive($defaultBodyAttrs, $rawBodyTagAttrs));
        }

        $content['seo']['htmlAttrs'] = array_merge([
            'lang' => $language->getLocale()->getLanguageCode(),
            'dir' => $language->getLocale()->isRightToLeftLanguageDirection() ? 'rtl' : null,
        ], $htmlTagAttrs);

        $content['seo']['bodyAttrs'] = $this->normalizeAttr($bodyTagAttrs);

        return $content;
    }

    /**
     * Generate page title using PageTitleProviderManager
     */
    protected function generatePageTitle(ServerRequestInterface $request, array $typoScriptConfig): string
    {
        return $this->pageTitleProviderManager->getTitle($request);
    }

    /**
     * Create ContentObjectRenderer instance for TypoScript processing
     */
    protected function createContentObjectRenderer(ServerRequestInterface $request, array $page): ContentObjectRenderer
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->setRequest($request);
        $cObj->start($page, 'pages');
        return $cObj;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function generateMetaTagsFromTyposcript(array $metaTagTypoScript, ContentObjectRenderer $cObj)
    {
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
        $conf = $typoScriptService->convertTypoScriptArrayToPlainArray($metaTagTypoScript);
        foreach ($conf as $key => $properties) {
            $replace = false;
            if (is_array($properties)) {
                $nodeValue = $properties['_typoScriptNodeValue'] ?? '';
                $value = trim((string)$cObj->stdWrap($nodeValue, $metaTagTypoScript[$key . '.']));
                if ($value === '' && !empty($properties['value'])) {
                    $value = $properties['value'];
                    $replace = false;
                }
            } else {
                $value = $properties;
            }

            $attribute = 'name';
            if ((is_array($properties) && !empty($properties['httpEquivalent'])) || strtolower($key) === 'refresh') {
                $attribute = 'http-equiv';
            }
            if (is_array($properties) && !empty($properties['attribute'])) {
                $attribute = $properties['attribute'];
            }
            if (is_array($properties) && !empty($properties['replace'])) {
                $replace = true;
            }

            if (!is_array($value)) {
                $value = (array)$value;
            }
            foreach ($value as $subValue) {
                if (trim($subValue ?? '') !== '') {
                    $this->setMetaTag($attribute, $key, $subValue, [], $replace);
                }
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function setMetaTag(
        string $type,
        string $name,
        string $content,
        array $subProperties = [],
        $replace = true
    ): void {
        $type = strtolower($type);
        $name = strtolower($name);
        if (!in_array($type, ['property', 'name', 'http-equiv'], true)) {
            throw new InvalidArgumentException(
                'When setting a meta tag the only types allowed are property, name or http-equiv. "' . $type . '" given.',
                1496402460
            );
        }
        $manager = $this->metaTagRegistry->getManagerForProperty($name);
        $manager->addProperty($name, $content, $subProperties, $replace, $type);
    }

    /**
     * @codeCoverageIgnore
     */
    private function normalizeAttr(array $rawHtmlAttrs): array
    {
        $htmlAttrs = [];

        foreach ($rawHtmlAttrs as $attr => $value) {
            $htmlAttrs[htmlspecialchars((string)$attr)] = htmlspecialchars((string)$value);
        }
        return $htmlAttrs;
    }
}
