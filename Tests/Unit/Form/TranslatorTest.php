<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form;

use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;
use FriendsOfTYPO3\Headless\Form\Translator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class TranslatorTest extends UnitTestCase
{
    public function test(): void
    {
        $translationService = $this->createMock(FormTranslationService::class);
        $translationService->method('translateElementValue')->willReturn('translatedValue');
        $translationService->method('translateElementError')->willReturn('translatedError');

        $translator = new Translator($translationService);

        $formDefinition = [
            'identifier' => 'testForm',
            'i18n' => ['properties' => ['test' => 'translateMe']],
            'renderables' => [
                0 =>
                    [
                        'renderables' => [
                            [
                                'type' => 'input',
                                'identifier' => 'testfield',
                                'label' => 'test field',
                                'properties' => [],
                                'value' => null,
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'testfield',
                                'label' => 'test field',
                                'properties' => [
                                    'validationErrorMessages' => [
                                        ['code' => 111, 'message' => 'translateMe'],
                                    ],
                                ],
                                'value' => null,
                            ],
                            [
                                'type' => 'Fieldset',
                                'identifier' => 'Fieldset',
                                'label' => 'Fieldset',
                                'properties' => [],
                                'renderables' => [
                                    [
                                        'type' => 'input',
                                        'identifier' => 'nested',
                                        'label' => 'nested',
                                        'properties' => [],
                                    ],
                                ],
                                'value' => null,
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'validators',
                                'label' => 'validators',
                                'properties' => [],
                                'validators' => [
                                    [
                                        'identifier' => 'RegularExpression',
                                        'options' => ['regularExpression' => '/a-b/'],
                                        'FERegularExpression' => '/a-z/',
                                        'errorMessage' => 111,
                                    ],
                                    [
                                        'identifier' => 'RegularExpression',
                                        'options' => ['regularExpression' => '/a-b/'],
                                        'FERegularExpression' => '/a-z/',
                                    ],
                                ],
                                'value' => null,
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'overridden',
                                'label' => 'overridden field',
                                'properties' => [],
                                'renderingOptions' => ['FEOverrideType' => 'select'],
                                'value' => null,
                            ],
                            [
                                'type' => 'ImageUpload',
                                'identifier' => 'image',
                                'label' => 'Upload image',
                                'properties' => ['saveToFileMount' => '/upload-dir'],
                                'value' => null,
                            ],
                        ],
                    ],
                1 => [],
            ],
        ];

        self::assertSame([
            'identifier' => 'testForm',
            'i18n' => ['properties' => ['test' =>'translatedValue']],
            'renderables' => [
                0 =>
                    [
                        'renderables' => [
                            [
                                'type' => 'input',
                                'identifier' => 'testfield',
                                'label' => 'translatedValue',
                                'properties' => [],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'testfield',
                                'label' => 'translatedValue',
                                'properties' => [
                                        'validationErrorMessages' => [
                                            ['code' => 111, 'message' => 'translatedError'],
                                        ],
                                ],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                            [
                                'type' => 'Fieldset',
                                'identifier' => 'Fieldset',
                                'label' => 'translatedValue',
                                'properties' => [],
                                'renderables' => [
                                    [
                                        'type' => 'input',
                                        'identifier' => 'nested',
                                        'label' => 'translatedValue',
                                        'properties' => [],
                                        'defaultValue' => 'translatedValue',
                                        'value' => null,
                                    ],
                                ],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'validators',
                                'label' => 'translatedValue',
                                'properties' => [],
                                'validators' => [
                                    [
                                        'identifier' => 'RegularExpression',
                                        'options' => ['regularExpression' => '/a-b/'],
                                        'FERegularExpression' => '/a-z/',
                                        'errorMessage' => 'translatedError',
                                    ],
                                    [
                                        'identifier' => 'RegularExpression',
                                        'options' => ['regularExpression' => '/a-b/'],
                                        'FERegularExpression' => '/a-z/',
                                    ],
                                ],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'overridden',
                                'label' => 'translatedValue',
                                'properties' => [],
                                'renderingOptions' => ['FEOverrideType' => 'select'],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                            [
                                'type' => 'ImageUpload',
                                'identifier' => 'image',
                                'label' => 'translatedValue',
                                'properties' => ['saveToFileMount' => 'translatedValue'],
                                'value' => null,
                                'defaultValue' => 'translatedValue',
                            ],
                        ],
                        'label' => 'translatedValue',

                    ],
            ],
        ], $translator->translate($formDefinition, []));
    }
}
