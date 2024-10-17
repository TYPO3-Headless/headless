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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class DatabaseQueryProcessorTest extends UnitTestCase
{
    use ProphecyTrait;

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
        $this->contentObjectRenderer->getRequest()->willReturn(new ServerRequest());
        $this->subject = $this->getAccessibleMock(DatabaseQueryProcessor::class, ['createContentObjectRenderer'], [
            $this->contentDataProcessor->reveal(),
            $this->typoScriptService->reveal(),
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
        $this->contentObjectRenderer->checkIf($processorConfiguration['if.'])->shouldBeCalledOnce()->willReturn(false);
        $this->contentObjectRenderer->stdWrapValue(Argument::any())->shouldNotBeCalled();
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }

    public function testReturnEarlyNoTableIsGiven(): void
    {
        $processorConfiguration = [
            'if.' => [
                'fooIsTrue',
            ],
        ];
        $processedData = [];
        $this->contentObjectRenderer->checkIf($processorConfiguration['if.'])->shouldBeCalledOnce()->willReturn(true);
        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('');
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
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

        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('tt_content');

        $this->contentObjectRenderer->stdWrapValue('as', $processorConfigurationWithoutTable, 'records')->shouldBeCalledOnce()->willReturn('records');

        $contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        $contentObjectRenderer->getRequest()->willReturn(new ServerRequest());
        $contentObjectRenderer->setRequest(Argument::type(ServerRequest::class))->shouldBeCalledOnce();
        $contentObjectRenderer->cObjGetSingle(Argument::any(), [])->willReturn(json_encode([ 'uid' => 1]));
        $contentObjectRenderer->start($records[0], 'tt_content')->shouldBeCalledOnce();
        $this->subject->expects(self::any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer->reveal());

        $this->contentDataProcessor->process($contentObjectRenderer, $processorConfigurationWithoutTable, $records[0])->shouldBeCalledOnce()->willReturn($records[0]);
        $this->contentObjectRenderer->getRecords('tt_content', $processorConfigurationWithoutTable)->shouldBeCalledOnce()->willReturn($records);

        $processedData['records'] = $records;
        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
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

        $this->contentObjectRenderer->stdWrapValue('table', $processorConfiguration)->shouldBeCalledOnce()->willReturn('tt_content');

        $this->contentObjectRenderer->stdWrapValue('as', $processorConfigurationWithoutTable, 'records')->shouldBeCalledOnce()->willReturn('records');

        $contentObjectRenderer = $this->prophesize(ContentObjectRenderer::class);
        $contentObjectRenderer->getRequest()->willReturn(new ServerRequest());
        $contentObjectRenderer->setRequest(Argument::type(ServerRequest::class))->shouldBeCalledOnce();
        $this->subject->expects(self::any())->method('createContentObjectRenderer')->willReturn($contentObjectRenderer->reveal());

        $expectedRecords = [
            [
                'title' => 'title',
            ],
        ];

        $this->contentObjectRenderer->getRecords('tt_content', $processorConfigurationWithoutTable)->shouldBeCalledOnce()->willReturn($records);
        $this->contentDataProcessor->process($contentObjectRenderer, $processorConfigurationWithoutTable, $expectedRecords[0])->shouldBeCalledOnce()->willReturn($expectedRecords[0]);

        $this->typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.'])->shouldBeCalledOnce()->willReturn($fields);
        $this->typoScriptService->convertPlainArrayToTypoScriptArray(['fields' => $fields, '_typoScriptNodeValue' => 'JSON'])->shouldBeCalledOnce()->willReturn($jsonCE);

        $contentObjectRenderer->start($records[0], $processorConfiguration['table'])->shouldBeCalledOnce();
        $contentObjectRenderer->setRequest(Argument::type(ServerRequest::class))->shouldBeCalledOnce();
        $contentObjectRenderer->cObjGetSingle('JSON', $jsonCE)->willReturn('{"title":"title"}');

        $processedData['records'] = $expectedRecords;

        self::assertEquals($processedData, $this->subject->process($this->contentObjectRenderer->reveal(), [], $processorConfiguration, $processedData));
    }
}
