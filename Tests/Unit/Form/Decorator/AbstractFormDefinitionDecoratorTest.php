<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form\Decorator;

use FriendsOfTYPO3\Headless\Form\Decorator\AbstractFormDefinitionDecorator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AbstractFormDefinitionDecoratorTest extends UnitTestCase
{
    /**
     * @test
     */
    public function basicOutput(): void
    {
        $stub = $this->getMockForAbstractClass(AbstractFormDefinitionDecorator::class, [['api' => 'test']]);

        $definition = [
            'identifier' => 'test-123',
            'renderables' => [0 => ['renderables' => []]],
            'i18n' => ['properties' => []],
        ];
        $test = $stub($definition, 1);
        self::assertSame(['id' => 'test-123', 'api' => ['api' => 'test'], 'i18n' => [], 'elements' => []], $test);
    }

    /**
     * @test
     */
    public function renderElements(): void
    {
        $stub = $this->getMockForAbstractClass(AbstractFormDefinitionDecorator::class, [['api' => 'test']]);

        $definition = [
            'identifier' => 'test-123',
            'renderables' => [
                0 =>
                    [
                        'renderables' => [
                            [
                                'type' => 'input',
                                'identifier' => 'testfield',
                                'label' => 'test field',
                            ],
                            [
                                'type' => 'Fieldset',
                                'identifier' => 'Fieldset',
                                'label' => 'Fieldset',
                                'renderables' => [
                                    [
                                        'type' => 'input',
                                        'identifier' => 'nested',
                                        'label' => 'nested',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'validators',
                                'label' => 'validators',
                                'validators' => [
                                    [
                                        'identifier' => 'RegularExpression',
                                        'options' => ['regularExpression' => '/a-b/'],
                                        'FERegularExpression' => '/a-z/',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'input',
                                'identifier' => 'overridden',
                                'label' => 'overridden field',
                                'renderingOptions' => ['FEOverrideType' => 'select'],
                            ],
                            [
                                'type' => 'ImageUpload',
                                'identifier' => 'image',
                                'label' => 'Upload image',
                                'properties' => ['saveToFileMount' => '/upload-dir'],
                            ],
                        ],
                    ],
            ],
            'i18n' => ['properties' => []],
        ];
        $test = $stub($definition, 0);

        self::assertSame([
            'id' => 'test-123',
            'api' => ['api' => 'test'],
            'i18n' => [],
            'elements' => [
                [
                    'type' => 'input',
                    'identifier' => 'testfield',
                    'label' => 'test field',
                    'name' => 'tx_form_formframework[test-123][testfield]',
                ],
                [
                    'type' => 'Fieldset',
                    'identifier' => 'Fieldset',
                    'label' => 'Fieldset',
                    'elements' => [
                        [
                            'type' => 'input',
                            'identifier' => 'nested',
                            'label' => 'nested',
                            'name' => 'tx_form_formframework[test-123][nested]',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'identifier' => 'validators',
                    'label' => 'validators',
                    'validators' => [
                        [
                            'identifier' => 'RegularExpression',
                            'options' => ['regularExpression' => '/a-z/'],
                        ],
                    ],
                    'name' => 'tx_form_formframework[test-123][validators]',
                ],
                [
                    'type' => 'select',
                    'identifier' => 'overridden',
                    'label' => 'overridden field',
                    'renderingOptions' => [],
                    'name' => 'tx_form_formframework[test-123][overridden]',
                ],
                [
                    'type' => 'ImageUpload',
                    'identifier' => 'image',
                    'label' => 'Upload image',
                    'properties' => [],
                    'name' => 'tx_form_formframework[test-123][image]',
                ],
            ],
        ], $test);
    }
}
