<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form\Service;

use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;

class FormTranslationServiceTest extends HeadlessUnitTestCase
{
    private const FORM_RUNTIME = [
        'identifier' => 'contact-42',
        'renderingOptions' => [
            '_originalIdentifier' => 'contact',
            'translation' => ['translationFiles' => ['EXT:site/Resources/Private/Language/forms.xlf']],
        ],
    ];

    public function testEmptyPropertyPartsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1476216007);

        $this->getService([])->translateElementValue(['identifier' => 'name'], [], self::FORM_RUNTIME);
    }

    public function testLabelFallsBackToElementValueWhenNoTranslationFound(): void
    {
        $service = $this->getService([]);

        $result = $service->translateElementValue(
            ['identifier' => 'name', 'type' => 'Text', 'label' => 'Default label'],
            ['label'],
            self::FORM_RUNTIME
        );

        self::assertSame('Default label', $result);
    }

    public function testLabelUsesTranslationAndPrefersOriginalIdentifierKey(): void
    {
        $chains = [];
        $service = $this->getService(['translated label'], $chains);

        $result = $service->translateElementValue(
            ['identifier' => 'name', 'type' => 'Text', 'label' => 'Default label'],
            ['label'],
            self::FORM_RUNTIME
        );

        self::assertSame('translated label', $result);
        self::assertSame(
            [
                'EXT:site/Resources/Private/Language/forms.xlf:contact.element.name.properties.label',
                'EXT:site/Resources/Private/Language/forms.xlf:contact-42.element.name.properties.label',
                'EXT:site/Resources/Private/Language/forms.xlf:element.name.properties.label',
                'EXT:site/Resources/Private/Language/forms.xlf:element.Text.properties.label',
            ],
            $chains[0]
        );
    }

    public function testPagePropertiesAreLookedUpInRenderingOptions(): void
    {
        $chains = [];
        $service = $this->getService(['Translated next'], $chains);

        $result = $service->translateElementValue(
            [
                'identifier' => 'page-1',
                'type' => 'Page',
                'renderingOptions' => ['nextButtonLabel' => 'next'],
            ],
            ['nextButtonLabel'],
            self::FORM_RUNTIME
        );

        self::assertSame('Translated next', $result);
        self::assertStringContainsString('.renderingOptions.nextButtonLabel', $chains[0][0]);
    }

    public function testSelectOptionsAreTranslatedPerOption(): void
    {
        $chains = [];
        $service = $this->getService(['One translated', null], $chains);

        $result = $service->translateElementValue(
            [
                'identifier' => 'salutation',
                'type' => 'SingleSelect',
                'properties' => ['options' => ['one' => 'One', 'two' => 'Two']],
            ],
            ['options'],
            self::FORM_RUNTIME
        );

        self::assertSame(['one' => 'One translated', 'two' => 'Two'], $result);
        self::assertStringContainsString('.properties.options.one', $chains[0][0]);
        self::assertStringContainsString('.properties.options.two', $chains[1][0]);
    }

    public function testTranslateElementErrorBuildsChainAndFallsBackToDefault(): void
    {
        $chains = [];
        $service = $this->getService([null], $chains);

        $result = $service->translateElementError(
            ['identifier' => 'email'],
            1221559976,
            self::FORM_RUNTIME,
            [],
            'Default error'
        );

        self::assertSame('Default error', $result);
        self::assertSame(
            [
                'EXT:site/Resources/Private/Language/forms.xlf:contact.validation.error.email.1221559976',
                'EXT:site/Resources/Private/Language/forms.xlf:contact.validation.error.1221559976',
                'EXT:site/Resources/Private/Language/forms.xlf:contact-42.validation.error.email.1221559976',
                'EXT:site/Resources/Private/Language/forms.xlf:contact-42.validation.error.1221559976',
                'EXT:site/Resources/Private/Language/forms.xlf:validation.error.email.1221559976',
                'EXT:site/Resources/Private/Language/forms.xlf:validation.error.1221559976',
            ],
            $chains[0]
        );
    }

    /**
     * @param list<string|null> $translations consecutive processTranslationChain() results
     * @param list<array<int, string>> $chains captured key chains, by call
     */
    private function getService(array $translations, array &$chains = []): FormTranslationService&MockObject
    {
        $service = $this->createPartialMock(FormTranslationService::class, ['processTranslationChain']);
        $callIndex = 0;
        $service->method('processTranslationChain')->willReturnCallback(
            static function (array $translationKeyChain) use (&$chains, &$callIndex, $translations) {
                $chains[] = $translationKeyChain;
                return $translations[$callIndex++] ?? null;
            }
        );

        return $service;
    }
}
