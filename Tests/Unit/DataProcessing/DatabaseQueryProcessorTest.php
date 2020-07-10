<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DatabaseQueryProcessorTest extends UnitTestCase
{
    /**
     * @var ObjectProphecy|TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var ObjectProphecy|ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * @var DatabaseQueryProcessor
     */
    protected $subject;

    /**
     * @var ObjectProphecy|ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    protected function setUp(): void
    {
        $this->typoScriptService = $this->prophesize(TypoScriptService::class);
        $this->contentDataProcessor = $this->prophesize(ContentDataProcessor::class);
        $this->contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        $this->subject = $this->getAccessibleMock(DatabaseQueryProcessor::class, ['createContentObjectRenderer'], [
            $this->contentDataProcessor->reveal(),
            $this->typoScriptService->reveal(),
        ]);
    }

    /**
     * @test
     */
    public function returnEarlyDueToIfStatementReturnsFalse(): void
    {
        $processorConfiguration = [
            'if.' => [
                'fooIsFalse',
            ],
        ];
        $processedData = [];
        $this->contentObjectRenderer->checkIf($processorConfiguration['if.'])->shouldBeCalledOnce()->willReturn(false);
        $this->contentObjectRenderer->stdWrapValue(Argument::any())->shouldNotBeCalled();
        $this->assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }

    /**
     * @test
     */
    public function returnEarlyNoTableIsGiven(): void
    {
        $processorConfiguration = [
            'if.' => [
                'fooIsTrue',
            ],
        ];
        $processedData = [];
        $this->contentObjectRenderer->checkIf($processorConfiguration['if.'])->shouldBeCalledOnce()->willReturn(true);
        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('');
        $this->assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }

    /**
     * @test
     */
    public function processWithoutAdditionalFields(): void
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

        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('tt_content');

        $this->contentObjectRenderer->stdWrapValue('as', $processorConfigurationWithoutTable, 'records')->shouldBeCalledOnce()->willReturn('records');

        $contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        $this->subject->expects($this->any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer->reveal());

        $this->contentDataProcessor->process($contentObjectRenderer, $processorConfigurationWithoutTable, $records[0])->shouldBeCalledOnce()->willReturn($records[0]);
        $this->contentObjectRenderer->getRecords('tt_content', $processorConfigurationWithoutTable)->shouldBeCalledOnce()->willReturn($records);

        $processedData['records'] = $records;
        $this->assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }

    /**
     * @test
     */
    public function processWithAdditionalFields(): void
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

        $fields = GeneralUtility::makeInstance(TypoScriptService::class)->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.']);

        $processedData = [];

        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('tt_content');

        $this->contentObjectRenderer->stdWrapValue('as', $processorConfigurationWithoutTable, 'records')->shouldBeCalledOnce()->willReturn('records');

        $contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        $this->subject->expects($this->any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer->reveal());

        $expectedRecords = [
            [
                'title' => 'title',
            ],
        ];

        $this->contentObjectRenderer->getRecords('tt_content', $processorConfigurationWithoutTable)->shouldBeCalledOnce()->willReturn($records);
        $this->contentDataProcessor->process($contentObjectRenderer, $processorConfigurationWithoutTable, $expectedRecords[0])->shouldBeCalledOnce()->willReturn($expectedRecords[0]);

        $this->typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.'])->shouldBeCalledOnce()->willReturn($fields);

        $contentObjectRenderer->start($records[0], $processorConfiguration['table'])->shouldBeCalledOnce();
        $contentObjectRenderer->cObjGetSingle($processorConfiguration['fields.']['title'], $processorConfiguration['fields.']['title.'])->willReturn('title');

        $processedData['records'] = $expectedRecords;

        $this->assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }
}
