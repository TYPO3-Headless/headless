<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo\MetaTag;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_merge;
use function json_decode;
use function json_encode;

/**
 * Overridden core version with headless implementation
 */
abstract class AbstractMetaTagManager extends \TYPO3\CMS\Core\MetaTag\AbstractMetaTagManager
{
    public function renderAllProperties(): string
    {
        if (GeneralUtility::makeInstance(HeadlessModeInterface::class)->withRequest($GLOBALS['TYPO3_REQUEST'])->isEnabled()) {
            return $this->renderAllHeadlessProperties();
        }

        return parent::renderAllProperties();
    }

    public function renderProperty(string $property): string
    {
        if (GeneralUtility::makeInstance(HeadlessModeInterface::class)->withRequest($GLOBALS['TYPO3_REQUEST'])->isEnabled()) {
            return $this->renderHeadlessProperty($property);
        }

        return parent::renderProperty($property);
    }

    /**
     * Render a meta tag for a specific property
     *
     * @param string $property Name of the property
     */
    public function renderHeadlessProperty(string $property): string
    {
        $property = strtolower($property);
        $metaTags = [];

        $nameAttribute = $this->defaultNameAttribute;
        if (isset($this->handledProperties[$property]['nameAttribute'])
            && !empty((string)$this->handledProperties[$property]['nameAttribute'])) {
            $nameAttribute = (string)$this->handledProperties[$property]['nameAttribute'];
        }

        $contentAttribute = $this->defaultContentAttribute;
        if (isset($this->handledProperties[$property]['contentAttribute'])
            && !empty((string)$this->handledProperties[$property]['contentAttribute'])) {
            $contentAttribute = (string)$this->handledProperties[$property]['contentAttribute'];
        }

        if ($nameAttribute && $contentAttribute) {
            foreach ($this->getProperty($property) as $propertyItem) {
                $metaTags[] = [
                    htmlspecialchars($nameAttribute) => htmlspecialchars($property),
                    htmlspecialchars($contentAttribute) => htmlspecialchars($propertyItem['content']),
                ];

                if (!count($propertyItem['subProperties'])) {
                    continue;
                }
                foreach ($propertyItem['subProperties'] as $subProperty => $subPropertyItems) {
                    foreach ($subPropertyItems as $subPropertyItem) {
                        $metaTags[] = [
                            htmlspecialchars($nameAttribute) => htmlspecialchars($property . $this->subPropertySeparator . $subProperty),
                            htmlspecialchars($contentAttribute) => htmlspecialchars((string)$subPropertyItem),
                        ];
                    }
                }
            }
        }

        return json_encode($metaTags);
    }

    /**
     * Render all registered properties of this manager
     */
    public function renderAllHeadlessProperties(): string
    {
        $metatags = [];
        foreach (array_keys($this->properties) as $property) {
            $metatags = array_merge($metatags, json_decode($this->renderHeadlessProperty($property), true));
        }

        return json_encode($metatags);
    }
}
