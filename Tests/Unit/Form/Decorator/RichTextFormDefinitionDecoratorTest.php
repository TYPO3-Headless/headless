<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form\Decorator;

use FriendsOfTYPO3\Headless\Form\Decorator\RichTextFormDefinitionDecorator;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use ReflectionProperty;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\HtmlSanitizer\Sanitizer;

/**
 * RichTextFormDefinitionDecorator sanitises HTML in fields exposed by the v14.2
 * RTE-form integration (#108966) and adds a `<field>Format = 'html'` sibling so
 * SPA consumers know to render the value as markup rather than plain text.
 *
 * Tests stub the html-sanitizer to deterministic behaviour: it strips <script>
 * tags and returns everything else verbatim. That keeps these tests focused on
 * the decorator's path-walking + marker-adding logic, not on html-sanitizer
 * configuration drift.
 */
class RichTextFormDefinitionDecoratorTest extends HeadlessUnitTestCase
{
    public function testSanitizesStaticTextPropertiesTextAndAddsFormatMarker(): void
    {
        $decorator = $this->buildDecoratorWithFakeSanitizer();

        $result = $decorator([
            'identifier' => 'rich',
            'renderables' => [
                ['renderables' => [
                    [
                        'type' => 'StaticText',
                        'identifier' => 'intro',
                        'properties' => ['text' => '<p>Hi <script>alert(1)</script>there</p>'],
                    ],
                ]],
            ],
            'i18n' => ['properties' => []],
        ], 0);

        $element = $result['elements'][0];
        self::assertSame('<p>Hi there</p>', $element['properties']['text']);
        self::assertSame('html', $element['properties']['textFormat']);
    }

    public function testLeavesPlainTextValuesUntouched(): void
    {
        $decorator = $this->buildDecoratorWithFakeSanitizer();

        $result = $decorator([
            'identifier' => 'rich',
            'renderables' => [
                ['renderables' => [
                    [
                        'type' => 'StaticText',
                        'identifier' => 'intro',
                        'properties' => ['text' => 'No HTML here'],
                    ],
                ]],
            ],
            'i18n' => ['properties' => []],
        ], 0);

        $properties = $result['elements'][0]['properties'];
        self::assertSame('No HTML here', $properties['text']);
        self::assertArrayNotHasKey('textFormat', $properties, 'format marker must only be added when the value actually contains HTML');
    }

    public function testCheckboxLabelIsSanitisedAndMarked(): void
    {
        $decorator = $this->buildDecoratorWithFakeSanitizer();

        $result = $decorator([
            'identifier' => 'rich',
            'renderables' => [
                ['renderables' => [
                    [
                        'type' => 'Checkbox',
                        'identifier' => 'accept',
                        'label' => '<strong>Accept</strong> the <script>alert(1)</script>terms',
                    ],
                ]],
            ],
            'i18n' => ['properties' => []],
        ], 0);

        $element = $result['elements'][0];
        self::assertSame('<strong>Accept</strong> the terms', $element['label']);
        self::assertSame('html', $element['labelFormat']);
    }

    public function testAnyElementTypeLabelIsSanitisedAndMarked(): void
    {
        $decorator = $this->buildDecoratorWithFakeSanitizer();

        $result = $decorator([
            'identifier' => 'rich',
            'renderables' => [
                ['renderables' => [
                    [
                        'type' => 'Text',
                        'identifier' => 'name',
                        'label' => 'Name <a href="/regulations">Regulations</a><script>alert(1)</script>',
                    ],
                ]],
            ],
            'i18n' => ['properties' => []],
        ], 0);

        $element = $result['elements'][0];
        self::assertSame('Name <a href="/regulations">Regulations</a>', $element['label']);
        self::assertSame('html', $element['labelFormat']);
    }

    public function testFinisherSuccessMessageIsSanitisedInActionAfterSuccess(): void
    {
        $actionAfterSuccess = new \stdClass();
        $actionAfterSuccess->message = '<p>Thanks <script>alert(1)</script></p>';

        $decorator = $this->buildDecoratorWithFakeSanitizer(['actionAfterSuccess' => $actionAfterSuccess]);

        $decorator([
            'identifier' => 'rich',
            'renderables' => [['renderables' => []]],
            'i18n' => ['properties' => []],
        ], 0);

        self::assertSame('<p>Thanks </p>', $actionAfterSuccess->message);
        self::assertSame('html', $actionAfterSuccess->messageFormat);
    }

    public function testPlainTextFinisherMessageIsLeftUntouched(): void
    {
        $actionAfterSuccess = new \stdClass();
        $actionAfterSuccess->message = 'Plain thanks';

        $decorator = $this->buildDecoratorWithFakeSanitizer(['actionAfterSuccess' => $actionAfterSuccess]);

        $decorator([
            'identifier' => 'rich',
            'renderables' => [['renderables' => []]],
            'i18n' => ['properties' => []],
        ], 0);

        self::assertSame('Plain thanks', $actionAfterSuccess->message);
        self::assertObjectNotHasProperty('messageFormat', $actionAfterSuccess);
    }

    public function testResolvesTypolinksThroughParseFuncWithoutExtraSanitizePass(): void
    {
        $rawText = '<p><a href="t3://page?uid=5">Go</a></p>';
        $resolvedText = '<p><a href="https://front.tld/target">Go</a></p>';
        $parseFuncConf = ['tags.' => []];

        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupTree(new RootNode());
        $typoScript->setSetupArray(['lib.' => ['parseFunc_RTE.' => $parseFuncConf]]);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('frontend.typoscript', $typoScript);

        try {
            $contentObject = $this->createMock(ContentObjectRenderer::class);
            $contentObject->expects(self::once())->method('parseFunc')
                ->with($rawText, $parseFuncConf)
                ->willReturn($resolvedText);

            $decorator = $this->createPartialMock(
                RichTextFormDefinitionDecorator::class,
                ['getContentObjectRenderer']
            );
            $decorator->method('getContentObjectRenderer')->willReturn($contentObject);
            $decorator->__construct([]);

            // parseFunc sanitizes itself (htmlSanitize defaults to enabled),
            // so the decorator's own sanitizer must stay untouched.
            $sanitizer = $this->createMock(Sanitizer::class);
            $sanitizer->expects(self::never())->method('sanitize');
            (new ReflectionProperty(RichTextFormDefinitionDecorator::class, 'sanitizer'))
                ->setValue($decorator, $sanitizer);

            $result = $decorator([
                'identifier' => 'rich',
                'renderables' => [
                    ['renderables' => [
                        [
                            'type' => 'StaticText',
                            'identifier' => 'intro',
                            'properties' => ['text' => $rawText],
                        ],
                    ]],
                ],
                'i18n' => ['properties' => []],
            ], 0);

            $element = $result['elements'][0];
            self::assertSame($resolvedText, $element['properties']['text']);
            self::assertSame('html', $element['properties']['textFormat']);
        } finally {
            unset($GLOBALS['TYPO3_REQUEST']);
        }
    }

    private function buildDecoratorWithFakeSanitizer(array $formStatus = []): RichTextFormDefinitionDecorator
    {
        $decorator = new RichTextFormDefinitionDecorator($formStatus);
        $this->injectFakeSanitizer($decorator);

        return $decorator;
    }

    private function injectFakeSanitizer(RichTextFormDefinitionDecorator $decorator): void
    {
        $sanitizer = $this->createMock(Sanitizer::class);
        $sanitizer->method('sanitize')->willReturnCallback(
            static fn(string $html): string => preg_replace('#<script[^>]*>.*?</script>#', '', $html)
        );

        (new ReflectionProperty(RichTextFormDefinitionDecorator::class, 'sanitizer'))
            ->setValue($decorator, $sanitizer);
    }
}
