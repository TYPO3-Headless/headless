<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use FriendsOfTYPO3\Headless\ContentObject\FloatContentObject;
use FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentObject;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\CaseContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectArrayContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectArrayInternalContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\FilesContentObject;
use TYPO3\CMS\Frontend\ContentObject\FluidTemplateContentObject;
use TYPO3\CMS\Frontend\ContentObject\HierarchicalMenuContentObject;
use TYPO3\CMS\Frontend\ContentObject\ImageContentObject;
use TYPO3\CMS\Frontend\ContentObject\ImageResourceContentObject;
use TYPO3\CMS\Frontend\ContentObject\LoadRegisterContentObject;
use TYPO3\CMS\Frontend\ContentObject\RecordsContentObject;
use TYPO3\CMS\Frontend\ContentObject\RestoreRegisterContentObject;
use TYPO3\CMS\Frontend\ContentObject\ScalableVectorGraphicsContentObject;
use TYPO3\CMS\Frontend\ContentObject\TextContentObject;
use TYPO3\CMS\Frontend\ContentObject\UserContentObject;
use TYPO3\CMS\Frontend\ContentObject\UserInternalContentObject;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;
use function md5;

class JsonContentObjectTest extends UnitTestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;

        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.supportOldPageOutput'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = [
            'TEXT' => TextContentObject::class,
            'CASE' => CaseContentObject::class,
            'COBJ_ARRAY' => ContentObjectArrayContentObject::class,
            'COA' => ContentObjectArrayContentObject::class,
            'COA_INT' => ContentObjectArrayInternalContentObject::class,
            'USER' => UserContentObject::class,
            'USER_INT' => UserInternalContentObject::class,
            'FILES' => FilesContentObject::class,
            'IMAGE' => ImageContentObject::class,
            'IMG_RESOURCE' => ImageResourceContentObject::class,
            'CONTENT' => ContentContentObject::class,
            'RECORDS' => RecordsContentObject::class,
            'HMENU' => HierarchicalMenuContentObject::class,
            'CASEFUNC' => CaseContentObject::class,
            'LOAD_REGISTER' => LoadRegisterContentObject::class,
            'RESTORE_REGISTER' => RestoreRegisterContentObject::class,
            'FLUIDTEMPLATE' => FluidTemplateContentObject::class,
            'SVG' => ScalableVectorGraphicsContentObject::class,
            'JSON' => JsonContentObject::class,
            'CONTENT_JSON' => JsonContentContentObject::class,
            'INT' => IntegerContentObject::class,
            'FLOAT' => FloatContentObject::class,
            'BOOL' => BooleanContentObject::class,
        ];

        GeneralUtility::makeInstance(TimeTracker::class, false);
        $contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class, $this->prophesize(Container::class)->reveal());

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->uniqueHash()->willReturn(md5('123'));

        $GLOBALS['TSFE'] = $tsfe->reveal();

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start([], 'tt_content', $this->prophesize(ServerRequestInterface::class)->reveal());
        $this->contentObject = new JsonContentObject($contentObjectRenderer, $contentDataProcessor);
    }

    /**
     * @test
     */
    public function renderTest()
    {
        self::assertEquals('[]', $this->contentObject->render());
    }

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function renderWithProviderTest($argument, $result)
    {
        self::assertEquals($result, $this->contentObject->render($argument));
    }

    public function dataProvider(): array
    {
        return [
            [[], '[]'],
            [null, '[]'],
            [['stdWrap.' => ['wrap' => '{"wrapped":|}']], json_encode(['wrapped' => []])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['SomeCustomProcessing'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], json_encode(['test' => '1'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '']]], json_encode(['test' => ''])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1', 'boolval' => 1]]], json_encode(['test' => true])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '0', 'boolval' => 1]]], json_encode(['test' => false])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => 'false', 'boolval' => 1]]], json_encode(['test' => false])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '', 'ifEmptyReturnNull' => 0]]], json_encode(['test' => ''])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '', 'ifEmptyReturnNull' => 1]]], json_encode(['test' => null])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => null, 'stdWrap.' => ['ifEmpty' => '{}']]]], json_encode(['test' => new \stdClass()])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], json_encode(['test' => '1'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1', 'intval' => 1]]], json_encode(['test' => 1])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], '20' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '20.' => ['as' => 'sites']]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['fields.' => ['test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['returnNullIfDataProcessingEmpty'=>1, 'fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\EmptyDataProcessingExample', '10.' => ['as' => 'sites']]]]], json_encode(['test' => [null]])],
            [['fields.' => ['test' => 'INT', 'test.' => ['value' => 1]]], json_encode(['test' => 1])],
            [['fields.' => ['test' => 'BOOL', 'test.' => ['value' => 0]]], json_encode(['test' => false])],
            [['fields.' => ['test' => 'BOOL', 'test.' => ['value' => 1]]], json_encode(['test' => true])],
            [['fields.' => ['test' => 'BOOL', 'test.' => ['value' => 1], 'nested.' => ['fields.' => ['nestedTest' => 'INT', 'nestedTest.' => ['value' => 10]]] ]], json_encode(['test' => true, 'nested' => ['nestedTest' => 10]])],
            [['fields.' => ['test' => 'BOOL', 'test.' => ['value' => 1], 'nested.' => ['fields.' => ['nestedTest' => 'INT', 'nestedTest.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]]]], json_encode(['test' => true, 'nested' => ['nestedTest' => ['SomeCustomProcessing']]])],
            [['fields.' => ['test' => 'FLOAT', 'test.' => ['value' => 12.34]]], json_encode(['test' => 12.34])],
            [['fields.' => ['test' => 'USER_INT', 'test.' => ['userFunc' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\ExampleUserFunc->someUserFunc']]], json_encode(['test' => 'HEADLESS_INT_START<<<!--INT_SCRIPT.202cb962ac59075b964b07152d234b70-->>>HEADLESS_INT_END'])],
            [['fields.' => ['test' => 'USER', 'test.' => ['userFunc' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\ExampleUserFunc->someUserFunc']]], json_encode(['test' => ['test2' => 'someExtraCustomData']])],
        ];
    }
}
