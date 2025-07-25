<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ContentObject;

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use FriendsOfTYPO3\Headless\ContentObject\FloatContentObject;
use FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentObject;
use FriendsOfTYPO3\Headless\Json\JsonDecoder;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use stdClass;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\CaseContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectArrayContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectArrayInternalContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\FilesContentObject;
use TYPO3\CMS\Frontend\ContentObject\HierarchicalMenuContentObject;
use TYPO3\CMS\Frontend\ContentObject\ImageResourceContentObject;
use TYPO3\CMS\Frontend\ContentObject\LoadRegisterContentObject;
use TYPO3\CMS\Frontend\ContentObject\RecordsContentObject;
use TYPO3\CMS\Frontend\ContentObject\RestoreRegisterContentObject;
use TYPO3\CMS\Frontend\ContentObject\ScalableVectorGraphicsContentObject;
use TYPO3\CMS\Frontend\ContentObject\TextContentObject;
use TYPO3\CMS\Frontend\ContentObject\UserContentObject;
use TYPO3\CMS\Frontend\ContentObject\UserInternalContentObject;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use TYPO3\CMS\Frontend\DataProcessing\DataProcessorRegistry;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;
use function md5;

class JsonContentObjectTest extends UnitTestCase
{
    use ProphecyTrait;

    private JsonContentObject $contentObject;

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
            'IMG_RESOURCE' => ImageResourceContentObject::class,
            'CONTENT' => ContentContentObject::class,
            'RECORDS' => RecordsContentObject::class,
            'HMENU' => HierarchicalMenuContentObject::class,
            'CASEFUNC' => CaseContentObject::class,
            'LOAD_REGISTER' => LoadRegisterContentObject::class,
            'RESTORE_REGISTER' => RestoreRegisterContentObject::class,
            'SVG' => ScalableVectorGraphicsContentObject::class,
            'CONTENT_JSON' => JsonContentContentObject::class,
            'INT' => IntegerContentObject::class,
            'FLOAT' => FloatContentObject::class,
            'BOOL' => BooleanContentObject::class,
        ];

        $container = new Container();

        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::any())->willReturnArgument();

        $contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class, $container, $this->prophesize(DataProcessorRegistry::class)->reveal());

        $container->set(MarkerBasedTemplateService::class, new MarkerBasedTemplateService($this->prophesize(FrontendInterface::class)->reveal(), $this->prophesize(FrontendInterface::class)->reveal()));
        $container->set(TimeTracker::class, new TimeTracker(false));
        $container->set(EventDispatcherInterface::class, $eventDispatcher->reveal());
        $container->set(JsonContentObject::class, new JsonContentObject(
            $contentDataProcessor,
            new JsonEncoder(),
            new JsonDecoder(),
            new HeadlessUserInt()
        ));
        $container->set(JsonContentContentObject::class, new JsonContentContentObject(
            $container->get(TimeTracker::class),
            $container->get(EventDispatcherInterface::class),
            new JsonEncoder(),
            new HeadlessUserInt()
        ));
        $container->set(RecordsContentObject::class, new RecordsContentObject($container->get(TimeTracker::class)));
        $container->set(ContentContentObject::class, new ContentContentObject($container->get(TimeTracker::class), $container->get(EventDispatcherInterface::class)));
        GeneralUtility::setContainer($container);

        $request = new ServerRequest();
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->setRequest($request);
        $contentObjectRenderer->start([], 'tt_content');

        $factory = $this->prophesize(ContentObjectFactory::class);
        $factory->getContentObject(Argument::type('string'), Argument::type('object'), Argument::type('object'))
            ->will(static function ($args) use ($contentObjectRenderer, $request) {
                $obj = GeneralUtility::makeInstance($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][$args[0]]);
                $obj->setRequest($request);
                $obj->setContentObjectRenderer($contentObjectRenderer);

                return $obj;
            });

        $container = GeneralUtility::getContainer();
        $container->set(ContentObjectFactory::class, $factory->reveal());

        GeneralUtility::setContainer($container);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] as $class) {
            GeneralUtility::makeInstance($class);
        }

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->uniqueHash()->willReturn(md5('123'));

        $GLOBALS['TSFE'] = $tsfe->reveal();

        $this->contentObject = GeneralUtility::makeInstance(JsonContentObject::class);
        $this->contentObject->setRequest($request);
        $this->contentObject->setContentObjectRenderer($contentObjectRenderer);
    }

    public function testRender()
    {
        self::assertEquals('[]', $this->contentObject->render());
    }

    #[DataProvider('dataProvider')]
    public function testRenderWithProvider($argument, $result)
    {
        self::assertEquals($result, $this->contentObject->render($argument));
    }

    public static function dataProvider(): array
    {
        return [
            [[], '[]'],
            [null, '[]'],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '', 'ifEmptyUnsetKey' => 1]]], json_encode([])],
            [['nullableFieldsIfEmpty' => 'test,test3', 'fields.' => ['test' => 'TEXT', 'test.' => ['value' => ''], 'test2' => 'TEXT', 'test2.' => ['value' => '1'], 'test3' => 'TEXT', 'test3.' => ['value' => '']]], json_encode(['test' => null, 'test2' => '1', 'test3' => null])],
            [['fields.' => ['test' => 'INT', 'test.' => ['value' => '1', 'ifEmptyUnsetKey' => 1]]], json_encode(['test' => 1])],
            [['if.' => ['isTrue' => 0], 'fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], ''],
            [['if.' => ['isTrue' => 1], 'fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], json_encode(['test' => '1'])],
            [['stdWrap.' => ['wrap' => '{"wrapped":|}']], json_encode(['wrapped' => []])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['SomeCustomProcessing'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], json_encode(['test' => '1'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '']]], json_encode(['test' => ''])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1', 'boolval' => 1]]], json_encode(['test' => true])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '0', 'boolval' => 1]]], json_encode(['test' => false])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => 'false', 'boolval' => 1]]], json_encode(['test' => false])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '', 'ifEmptyReturnNull' => 0]]], json_encode(['test' => ''])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '', 'ifEmptyReturnNull' => 1]]], json_encode(['test' => null])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => null, 'stdWrap.' => ['ifEmpty' => '{}']]]], json_encode(['test' => new stdClass()])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']]], json_encode(['test' => '1'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1', 'intval' => 1]]], json_encode(['test' => 1])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], '20' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '20.' => ['as' => 'sites']]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['fields.' => ['test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['returnNullIfDataProcessingEmpty' => 1, 'fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\EmptyDataProcessingExample', '10.' => ['as' => 'sites']]]]], json_encode(['test' => [null]])],
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

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }
}
