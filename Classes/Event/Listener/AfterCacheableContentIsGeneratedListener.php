<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;

use function array_merge;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class AfterCacheableContentIsGeneratedListener
{
    public function __construct(
        private readonly JsonEncoder $encoder,
        private readonly MetaTagManagerRegistry $metaTagRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event)
    {
        try {
            if (!GeneralUtility::makeInstance(HeadlessMode::class)->withRequest($event->getRequest())->isEnabled()) {
                return;
            }

            $content = json_decode($event->getController()->content, true, 512, JSON_THROW_ON_ERROR);

            if (($content['seo']['title'] ?? null) === null) {
                return;
            }

            $_params = ['page' => $event->getController()->page, 'request' => $event->getRequest(), '_seoLinks' => []];
            $_ref = null;
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags'] ?? [] as $_funcRef) {
                GeneralUtility::callUserFunction($_funcRef, $_params, $_ref);
            }

            $content['seo']['title'] = $event->getController()->generatePageTitle();

            $this->generateMetaTagsFromTyposcript($event->getController()->pSetup['meta.'] ?? [], $event->getController()->cObj);

            $metaTags = [];
            $metaTagManagers = GeneralUtility::makeInstance(MetaTagManagerRegistry::class)->getAllManagers();

            foreach ($metaTagManagers as $manager => $managerObject) {
                $properties = json_decode($managerObject->renderAllProperties(), true);
                if (!empty($properties)) {
                    $metaTags = array_merge($metaTags, $properties);
                }
            }

            $content['seo']['meta'] = $metaTags;

            $hrefLangs = $this->eventDispatcher->dispatch(new ModifyHrefLangTagsEvent($event->getRequest()))->getHrefLangs();

            $seoLinks = $_params['_seoLinks'] ?? [];

            if (count($hrefLangs) > 1) {
                foreach ($hrefLangs as $hrefLang => $href) {
                    $seoLinks[] = ['rel' => 'alternate', 'hreflang' => $hrefLang, 'href' => $href];
                }
            }

            if ($seoLinks !== []) {
                $content['seo']['link'] = $seoLinks;
            }

            $event->getController()->content = $this->encoder->encode($content);
        } catch (\Throwable $e) {
            return;
        }
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
    private function setMetaTag(string $type, string $name, string $content, array $subProperties = [], $replace = true): void
    {
        $type = strtolower($type);
        $name = strtolower($name);
        if (!in_array($type, ['property', 'name', 'http-equiv'], true)) {
            throw new \InvalidArgumentException(
                'When setting a meta tag the only types allowed are property, name or http-equiv. "' . $type . '" given.',
                1496402460
            );
        }
        $manager = $this->metaTagRegistry->getManagerForProperty($name);
        $manager->addProperty($name, $content, $subProperties, $replace, $type);
    }
}
