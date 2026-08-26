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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Type\DocType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_merge;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Overridden core version with headless implementation
 */
abstract class AbstractMetaTagManager extends \TYPO3\CMS\Core\MetaTag\AbstractMetaTagManager
{
    protected ?HeadlessModeInterface $headlessMode = null;

    protected function getHeadlessMode(): HeadlessModeInterface
    {
        return $this->headlessMode ??= GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    public function renderAllProperties(?DocType $docType = null): string
    {
        if ($this->isHeadlessRequest()) {
            return $this->renderAllHeadlessProperties();
        }

        return parent::renderAllProperties($docType);
    }

    public function renderProperty(string $property, ?DocType $docType = null): string
    {
        if ($this->isHeadlessRequest()) {
            return $this->renderHeadlessProperty($property);
        }

        return parent::renderProperty($property, $docType);
    }

    protected function isHeadlessRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        return $request instanceof ServerRequestInterface
            && $this->getHeadlessMode()->isEnabledFor($request);
    }

    /**
     * Render a meta tag for a specific property
     *
     * @param string $property Name of the property
     */
    public function renderHeadlessProperty(string $property): string
    {
        return json_encode($this->renderHeadlessPropertyAsArray($property), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function renderHeadlessPropertyAsArray(string $property): array
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

        if (!$nameAttribute || !$contentAttribute) {
            return $metaTags;
        }

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

        return $metaTags;
    }

    /**
     * Render all registered properties of this manager
     */
    public function renderAllHeadlessProperties(): string
    {
        return json_encode($this->renderAllHeadlessPropertiesAsArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function renderAllHeadlessPropertiesAsArray(): array
    {
        $metaTags = [];
        foreach (array_keys($this->properties) as $property) {
            $metaTags = array_merge($metaTags, $this->renderHeadlessPropertyAsArray($property));
        }
        return $metaTags;
    }
}
