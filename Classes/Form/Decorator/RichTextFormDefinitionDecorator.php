<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Decorator;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Html\SanitizerBuilderFactory;
use TYPO3\CMS\Core\Html\SanitizerInitiator;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\HtmlSanitizer\Sanitizer;

use function is_array;
use function is_object;
use function is_string;
use function str_contains;

/**
 * Decorator for forms that opt into TYPO3 v14.2 RTE-enabled fields (#108966).
 *
 * Enable per form (Form YAML):
 *
 *   renderingOptions:
 *     formDecorator: FriendsOfTYPO3\Headless\Form\Decorator\RichTextFormDefinitionDecorator
 */
class RichTextFormDefinitionDecorator extends AbstractFormDefinitionDecorator
{
    private ?Sanitizer $sanitizer = null;

    protected function overrideElement(array $element): array
    {
        $label = $element['label'] ?? null;
        if (is_string($label) && str_contains($label, '<')) {
            $element['label'] = $this->processRichText($label);
            $element['labelFormat'] = 'html';
        }

        $text = $element['properties']['text'] ?? null;
        if (($element['type'] ?? '') === 'StaticText' && is_string($text) && str_contains($text, '<')) {
            $element['properties']['text'] = $this->processRichText($text);
            $element['properties']['textFormat'] = 'html';
        }

        return $element;
    }

    /**
     * @param array<string, mixed> $decorated
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    protected function overrideDefinition(array $decorated, array $definition, int $currentPage): array
    {
        $actionAfterSuccess = $decorated['api']['actionAfterSuccess'] ?? null;
        $message = $actionAfterSuccess->message ?? null;

        if (is_object($actionAfterSuccess) && is_string($message) && str_contains($message, '<')) {
            $actionAfterSuccess->message = $this->processRichText($message);
            $actionAfterSuccess->messageFormat = 'html';
        }

        return $decorated;
    }

    protected function processRichText(string $html): string
    {
        return $this->resolveUrls($html) ?? $this->sanitize($html);
    }

    protected function resolveUrls(string $html): ?string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        $typoScript = $request->getAttribute('frontend.typoscript');

        if (!$typoScript instanceof FrontendTypoScript || !$typoScript->hasSetup()) {
            return null;
        }

        $setup = $typoScript->getSetupArray();
        $parseFuncConf = $setup['lib.']['parseFunc_RTE.'] ?? $setup['lib.']['parseFunc_links.'] ?? null;

        if (!is_array($parseFuncConf)) {
            return null;
        }

        $contentObject = $this->getContentObjectRenderer();
        $contentObject->setRequest($request);
        $contentObject->start([]);

        return (string)$contentObject->parseFunc($html, $parseFuncConf);
    }

    protected function getContentObjectRenderer(): ContentObjectRenderer
    {
        return GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }

    protected function sanitize(string $html): string
    {
        if ($this->sanitizer === null) {
            $factory = GeneralUtility::makeInstance(SanitizerBuilderFactory::class);
            $this->sanitizer = $factory->build('default')->build();
        }

        return $this->sanitizer->sanitize(
            $html,
            GeneralUtility::makeInstance(SanitizerInitiator::class, self::class)
        );
    }
}
