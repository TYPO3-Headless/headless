<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class DatabaseQueryProcessorTest extends UnitTestCase
{
    protected TypoScriptService $typoScriptService;

    /**
     * @var MockObject&ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * @var DatabaseQueryProcessor
     */
    protected $subject;

    /**
     * @var MockObject&ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    protected function setUp(): void
    {
        $this->typoScriptService = new TypoScriptService();
        $this->contentDataProcessor = $this->createMock(ContentDataProcessor::class);
        $this->contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $this->contentObjectRenderer->method('getRequest')->willReturn(new ServerRequest());
        $this->subject = $this->getAccessibleMock(DatabaseQueryProcessor::class, ['createContentObjectRenderer'], [
            $this->contentDataProcessor,
            $this->typoScriptService,
        ]);
        parent::setUp();
    }

    public function testReturnEarlyDueToIfStatementReturnsFalse(): void
    {
        $processorConfiguration = [
            'if.' => [
                'fooIsFalse',
            ],
        ];
        $processedData = [];
        $this->contentObjectRenderer->expects(self::once())->method('checkIf')->with($processorConfiguration['if.'])->willReturn(false);
        $this->contentObjectRenderer->expects(self::never())->method('stdWrapValue');
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer, [], $processorConfiguration, $processedData));
    }

    public function testReturnEarlyNoTableIsGiven(): void
    {
        $processorConfiguration = [
            'if.' => [
                'fooIsTrue',
            ],
        ];
        $processedData = [];
        $this->contentObjectRenderer->expects(self::once())->method('checkIf')->with($processorConfiguration['if.'])->willReturn(true);
        $this->contentObjectRenderer->expects(self::once())->method('stdWrapValue')->with('table', $processorConfiguration)->willReturn('');
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer, [], $processorConfiguration, $processedData));
    }

    public function testProcessWithoutAdditionalFields(): void
    {
        $processorConfiguration = [
            'table' => 'tt_content',
        ];

        $processorConfigurationWithoutTable = $processorConfiguration;
        unset($processorConfigurationWithoutTable['table']);

        $processedData = [];

        $records = [
            [
                'uid' => 1,
            ],
        ];

        $this->contentObjectRenderer->method('stdWrapValue')->willReturnMap([
            ['table', $processorConfiguration, '', null, 'tt_content'],
            ['as', $processorConfigurationWithoutTable, 'records', null, 'records'],
        ]);

        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('getRequest')->willReturn(new ServerRequest());
        $contentObjectRenderer->expects(self::once())->method('setRequest')->with(self::isInstanceOf(ServerRequest::class));
        $contentObjectRenderer->method('cObjGetSingle')->with(self::anything(), [])->willReturn(json_encode(['uid' => 1]));
        $contentObjectRenderer->expects(self::once())->method('start')->with($records[0], 'tt_content');
        $this->subject->expects(self::any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer);

        $this->contentDataProcessor->expects(self::once())->method('process')->with($contentObjectRenderer, $processorConfigurationWithoutTable, $records[0])->willReturn($records[0]);
        $this->contentObjectRenderer->expects(self::once())->method('getRecords')->with('tt_content', $processorConfigurationWithoutTable)->willReturn($records);

        $processedData['records'] = $records;
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer, [], $processorConfiguration, $processedData));
    }

    public function testProcessWithAdditionalFields(): void
    {
        $processorConfiguration = [
            'table' => 'tt_content',
            'fields.' => [
                'title' => 'TEXT',
                'title.' => [
                    'field' => 'header',
                ],
            ],
        ];

        $processorConfigurationWithoutTable = $processorConfiguration;
        unset($processorConfigurationWithoutTable['table']);

        $records = [
            [
                'uid' => 1,
            ],
        ];

        $typoscriptService = GeneralUtility::makeInstance(TypoScriptService::class);

        $fields = $typoscriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.']);
        $jsonCE = $typoscriptService->convertPlainArrayToTypoScriptArray(['fields' => $fields, '_typoScriptNodeValue' => 'JSON']);
        $processedData = [];

        $this->contentObjectRenderer->method('stdWrapValue')->willReturnMap([
            ['table', $processorConfiguration, '', null, 'tt_content'],
            ['as', $processorConfigurationWithoutTable, 'records', null, 'records'],
        ]);

        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->method('getRequest')->willReturn(new ServerRequest());
        $contentObjectRenderer->method('setRequest')->with(self::isInstanceOf(ServerRequest::class));
        $this->subject->expects(self::any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer);

        $expectedRecords = [
            [
                'title' => 'title',
            ],
        ];

        $this->contentObjectRenderer->expects(self::once())->method('getRecords')->with('tt_content', $processorConfigurationWithoutTable)->willReturn($records);
        $this->contentDataProcessor->expects(self::once())->method('process')->with($contentObjectRenderer, $processorConfigurationWithoutTable, $expectedRecords[0])->willReturn($expectedRecords[0]);

        $contentObjectRenderer->expects(self::once())->method('start')->with($records[0], $processorConfiguration['table']);
        $contentObjectRenderer->method('cObjGetSingle')->with('JSON', $jsonCE)->willReturn('{"title":"title"}');

        $processedData['records'] = $expectedRecords;

        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer, [], $processorConfiguration, $processedData));
    }
}
