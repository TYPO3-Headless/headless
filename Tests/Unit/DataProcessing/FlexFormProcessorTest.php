<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class FlexFormProcessorTest extends HeadlessUnitTestCase
{
    public function testMissingFieldIsPassedThrough(): void
    {
        $processedData = ['data' => ['uid' => 1]];

        self::assertSame(
            $processedData,
            $this->getProcessor()->process($this->getContentObjectRenderer(), [], [], $processedData)
        );
    }

    public function testEmptyValueIsPassedThrough(): void
    {
        $processedData = ['data' => ['pi_flexform' => '']];

        self::assertSame(
            $processedData,
            $this->getProcessor()->process($this->getContentObjectRenderer(), [], [], $processedData)
        );
    }

    public function testStringValueIsConvertedAndStoredInDataField(): void
    {
        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->expects(self::once())
            ->method('convertFlexFormContentToArray')
            ->with('<T3FlexForms>…</T3FlexForms>')
            ->willReturn(['settings' => ['limit' => 5]]);

        $result = $this->getProcessor($flexFormTools)->process(
            $this->getContentObjectRenderer(),
            [],
            [],
            ['data' => ['pi_flexform' => '<T3FlexForms>…</T3FlexForms>']]
        );

        self::assertSame(['settings' => ['limit' => 5]], $result['data']['pi_flexform']);
    }

    public function testConvertedValueIsStoredUnderConfiguredTargetVariable(): void
    {
        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->method('convertFlexFormContentToArray')->willReturn(['settings' => 1]);

        $result = $this->getProcessor($flexFormTools)->process(
            $this->getContentObjectRenderer(as: 'flexform'),
            [],
            [],
            ['data' => ['pi_flexform' => '<xml/>']]
        );

        self::assertSame(['settings' => 1], $result['flexform']);
        self::assertSame('<xml/>', $result['data']['pi_flexform']);
    }

    public function testTopLevelFieldIsConvertedAndStoredBackInPlace(): void
    {
        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->method('convertFlexFormContentToArray')->willReturn(['settings' => 1]);

        $result = $this->getProcessor($flexFormTools)->process(
            $this->getContentObjectRenderer(),
            [],
            [],
            ['pi_flexform' => '<xml/>']
        );

        self::assertSame(['settings' => 1], $result['pi_flexform']);
        self::assertArrayNotHasKey('data', $result);
    }

    public function testCustomFieldNameIsRead(): void
    {
        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->method('convertFlexFormContentToArray')->willReturn(['x' => 1]);

        $result = $this->getProcessor($flexFormTools)->process(
            $this->getContentObjectRenderer(fieldName: 'tx_custom_flex'),
            [],
            [],
            ['data' => ['tx_custom_flex' => '<xml/>']]
        );

        self::assertSame(['x' => 1], $result['data']['tx_custom_flex']);
    }

    public function testArrayValueSkipsConversion(): void
    {
        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->expects(self::never())->method('convertFlexFormContentToArray');

        $result = $this->getProcessor($flexFormTools)->process(
            $this->getContentObjectRenderer(as: 'flexform'),
            [],
            [],
            ['data' => ['pi_flexform' => ['already' => 'converted']]]
        );

        self::assertSame(['already' => 'converted'], $result['flexform']);
    }

    public function testOverrideFieldsAreRenderedAndMergedIntoFlexFormData(): void
    {
        $request = new ServerRequest();
        $flexformData = ['header' => 'raw', 'existing' => 1];
        $recordData = ['uid' => 42, 'pid' => 1];

        $recordContentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $recordContentObjectRenderer->expects(self::once())->method('setRequest')->with($request);
        $recordContentObjectRenderer->expects(self::once())->method('start')
            ->with(array_merge($recordData, $flexformData));
        $recordContentObjectRenderer->expects(self::once())->method('cObjGetSingle')
            ->with('JSON', self::callback(static fn(array $conf): bool => isset($conf['fields.']['header'])))
            ->willReturn('{"header":"processed","image":[{"uid":7}]}');

        $container = new Container();
        $container->set(ContentObjectRenderer::class, $recordContentObjectRenderer);
        GeneralUtility::setContainer($container);

        $flexFormTools = $this->createMock(FlexFormTools::class);
        $flexFormTools->method('convertFlexFormContentToArray')->willReturn($flexformData);

        $contentObjectRenderer = $this->getContentObjectRenderer(as: 'flexform');
        $contentObjectRenderer->method('getRequest')->willReturn($request);
        $contentObjectRenderer->data = $recordData;

        $result = $this->getProcessor($flexFormTools)->process(
            $contentObjectRenderer,
            [],
            ['overrideFields.' => ['header' => 'TEXT', 'header.' => ['field' => 'header']]],
            ['data' => ['pi_flexform' => '<xml/>']]
        );

        self::assertSame(
            ['header' => 'processed', 'existing' => 1, 'image' => [['uid' => 7]]],
            $result['flexform']
        );
    }

    public function testProcessOverrideFieldsWithoutRequestSkipsRequestBinding(): void
    {
        $recordContentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $recordContentObjectRenderer->expects(self::never())->method('setRequest');
        $recordContentObjectRenderer->method('cObjGetSingle')->willReturn('{"header":"processed"}');

        $container = new Container();
        $container->set(ContentObjectRenderer::class, $recordContentObjectRenderer);
        GeneralUtility::setContainer($container);

        $result = $this->getProcessor()->processOverrideFields(
            ['uid' => 42],
            ['header' => 'raw'],
            ['overrideFields.' => ['header' => 'TEXT']],
            null
        );

        self::assertSame(['header' => 'processed'], $result);
    }

    private function getProcessor(?FlexFormTools $flexFormTools = null): FlexFormProcessor
    {
        return new FlexFormProcessor(
            $flexFormTools ?? $this->createMock(FlexFormTools::class),
            new TypoScriptService()
        );
    }

    private function getContentObjectRenderer(string $fieldName = '', string $as = ''): ContentObjectRenderer&MockObject
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('stdWrapValue')->willReturnCallback(
            static fn(string $key): string => $key === 'fieldName' ? $fieldName : ($key === 'as' ? $as : '')
        );

        return $contentObjectRenderer;
    }
}
