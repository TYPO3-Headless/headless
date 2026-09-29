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
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use TYPO3\CMS\Frontend\DataProcessing\DataProcessorRegistry;

use function json_encode;
use function md5;

class JsonContentObjectTest extends HeadlessUnitTestCase
{
    private JsonContentObject $contentObject;

    private CountingDataProcessingExample $countingProcessor;

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
            'JSON' => JsonContentObject::class,
            'INT' => IntegerContentObject::class,
            'FLOAT' => FloatContentObject::class,
            'BOOL' => BooleanContentObject::class,
        ];

        $container = new Container();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class, $container, $this->createMock(DataProcessorRegistry::class));

        $container->set(MarkerBasedTemplateService::class, new MarkerBasedTemplateService($this->createMock(FrontendInterface::class), $this->createMock(FrontendInterface::class)));
        $container->set(TimeTracker::class, new TimeTracker(false));
        $container->set(EventDispatcherInterface::class, $eventDispatcher);
        $createJsonContentObject = static fn(): JsonContentObject => new JsonContentObject(
            $contentDataProcessor,
            new JsonEncoder(new \TYPO3\CMS\Core\Configuration\Features()),
            new JsonDecoder(),
            new HeadlessUserInt()
        );
        $container->set(JsonContentObject::class, $createJsonContentObject());
        $container->set(JsonContentContentObject::class, new JsonContentContentObject(
            $container->get(TimeTracker::class),
            $container->get(EventDispatcherInterface::class),
            new JsonEncoder(new \TYPO3\CMS\Core\Configuration\Features()),
            new HeadlessUserInt(),
            $this->createMock(\TYPO3\CMS\Backend\View\BackendLayoutView::class)
        ));
        $container->set(RecordsContentObject::class, new RecordsContentObject($container->get(TimeTracker::class)));
        $container->set(ContentContentObject::class, new ContentContentObject($container->get(TimeTracker::class), $container->get(EventDispatcherInterface::class)));
        $container->set(DataProcessingExample::class, new DataProcessingExample());
        $container->set(EmptyDataProcessingExample::class, new EmptyDataProcessingExample());
        $container->set(MultiVariableDataProcessingExample::class, new MultiVariableDataProcessingExample());
        $this->countingProcessor = new CountingDataProcessingExample();
        $container->set(CountingDataProcessingExample::class, $this->countingProcessor);
        GeneralUtility::setContainer($container);

        $request = new ServerRequest();
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->data = [];
        $contentObjectRenderer->method('getRequest')->willReturn($request);
        $typoScriptSetup = [
            'lib.' => [
                'json' => 'JSON',
                'json.' => ['dataProcessing.' => ['10' => CountingDataProcessingExample::class, '10.' => ['as' => 'counter']]],
            ],
        ];
        $mergeTSRef = static function (array $typoScriptArray, string $propertyName) use ($typoScriptSetup): array {
            if (!str_starts_with($typoScriptArray[$propertyName], '<')) {
                return $typoScriptArray;
            }
            [$lib, $key] = explode('.', trim(substr($typoScriptArray[$propertyName], 1)), 2);
            $typoScriptArray[$propertyName] = $typoScriptSetup[$lib . '.'][$key];
            $typoScriptArray[$propertyName . '.'] = array_replace_recursive($typoScriptSetup[$lib . '.'][$key . '.'] ?? [], $typoScriptArray[$propertyName . '.'] ?? []);

            return $typoScriptArray;
        };
        $contentObjectRenderer->method('mergeTSRef')->willReturnCallback($mergeTSRef);
        $contentObjectRenderer->method('cObjGetSingle')->willReturnCallback(
            static function (string $name, array $conf) use ($contentObjectRenderer, $request, $mergeTSRef) {
                $resolved = $mergeTSRef(['tempKey' => $name, 'tempKey.' => $conf], 'tempKey');
                $name = $resolved['tempKey'];
                $conf = $resolved['tempKey.'];
                if ($name === 'JSON') {
                    return GeneralUtility::getContainer()->get(ContentObjectFactory::class)->getContentObject($name, $request, $contentObjectRenderer)->render($conf);
                }
                if ($name === 'USER_INT') {
                    return '<!--INT_SCRIPT.' . md5('123') . '-->';
                }
                if ($name === 'USER' && isset($conf['userFunc'])) {
                    [$class, $method] = explode('->', $conf['userFunc']);
                    $obj = new $class();
                    return $obj->$method('', $conf);
                }
                $value = $conf['value'] ?? '';
                if (isset($conf['stdWrap.']['ifEmpty']) && ($value === '' || $value === null)) {
                    $value = $conf['stdWrap.']['ifEmpty'];
                }
                return $value;
            }
        );
        $contentObjectRenderer->method('checkIf')->willReturnCallback(
            static function (array $conf) {
                return (bool)($conf['isTrue'] ?? false);
            }
        );
        $contentObjectRenderer->method('stdWrap')->willReturnCallback(
            static function (string $content, array $conf) {
                if (isset($conf['wrap'])) {
                    $parts = explode('|', $conf['wrap']);
                    return ($parts[0] ?? '') . $content . ($parts[1] ?? '');
                }
                return $content;
            }
        );
        $contentObjectRenderer->method('stdWrapValue')->willReturnCallback(
            static function (string $key, array $conf, $defaultValue = '') {
                return $conf[$key] ?? $defaultValue;
            }
        );

        $factory = $this->createMock(ContentObjectFactory::class);
        $factory->method('getContentObject')
            ->willReturnCallback(static function (string $name) use ($contentObjectRenderer, $request, $createJsonContentObject) {
                $obj = $name === 'JSON'
                    ? $createJsonContentObject()
                    : GeneralUtility::makeInstance($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][$name]);
                $obj->setRequest($request);
                $obj->setContentObjectRenderer($contentObjectRenderer);

                return $obj;
            });

        $container = GeneralUtility::getContainer();
        $container->set(ContentObjectFactory::class, $factory);

        GeneralUtility::setContainer($container);

        $this->contentObject = GeneralUtility::makeInstance(JsonContentObject::class);
        $this->contentObject->setRequest($request);
        $this->contentObject->setContentObjectRenderer($contentObjectRenderer);
    }

    public function testRender()
    {
        self::assertEquals('[]', $this->contentObject->render());
    }

    public function testJsonFieldRunsDataProcessingOnce(): void
    {
        $conf = ['fields.' => ['test' => 'JSON', 'test.' => ['dataProcessing.' => ['10' => CountingDataProcessingExample::class, '10.' => ['as' => 'counter']]]]];

        self::assertSame(json_encode(['test' => ['Counted']]), $this->contentObject->render($conf));
        self::assertSame(1, $this->countingProcessor->calls);
    }

    public function testPlainFieldRunsDataProcessingOnce(): void
    {
        $conf = ['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => CountingDataProcessingExample::class, '10.' => ['as' => 'counter']]]]];

        self::assertSame(json_encode(['test' => ['Counted']]), $this->contentObject->render($conf));
        self::assertSame(1, $this->countingProcessor->calls);
    }

    public function testReferencedJsonFieldRunsDataProcessingOnce(): void
    {
        $conf = ['fields.' => ['test' => '< lib.json']];

        self::assertSame(json_encode(['test' => ['Counted']]), $this->contentObject->render($conf));
        self::assertSame(1, $this->countingProcessor->calls);
    }

    public function testReferencedJsonFieldWithLocalProcessorRunsEachOnce(): void
    {
        $conf = ['fields.' => ['test' => '< lib.json', 'test.' => ['dataProcessing.' => ['20' => CountingDataProcessingExample::class, '20.' => ['as' => 'extra']]]]];

        self::assertSame(json_encode(['test' => ['Counted']]), $this->contentObject->render($conf));
        self::assertSame(2, $this->countingProcessor->calls);
    }

    public function testDeepNestingWithMultipleProcessorsRunsEachOnce(): void
    {
        $counting = CountingDataProcessingExample::class;
        $conf = [
            'dataProcessingMerge' => 1,
            'fields.' => [
                'level1' => 'JSON',
                'level1.' => [
                    'dataProcessingMerge' => 1,
                    'fields.' => [
                        'level2' => 'JSON',
                        'level2.' => [
                            'dataProcessingMerge' => 1,
                            'fields.' => [
                                'level3' => 'JSON',
                                'level3.' => ['dataProcessing.' => ['10' => $counting, '10.' => ['as' => 'a'], '20' => $counting, '20.' => ['as' => 'b']]],
                                'plain' => 'TEXT',
                                'plain.' => ['dataProcessing.' => ['10' => $counting, '10.' => ['as' => 'p']]],
                                'ref' => '< lib.json',
                                'ref.' => ['dataProcessing.' => ['20' => $counting, '20.' => ['as' => 'extra']]],
                            ],
                            'dataProcessing.' => ['10' => $counting, '10.' => ['as' => 'l2']],
                        ],
                    ],
                    'dataProcessing.' => ['10' => $counting, '10.' => ['as' => 'l1a'], '20' => $counting, '20.' => ['as' => 'l1b']],
                ],
            ],
            'dataProcessing.' => ['10' => $counting, '10.' => ['as' => 'top']],
        ];

        $expected = json_encode([
            'level1' => [
                'level2' => [
                    'level3' => ['Counted'],
                    'plain' => ['Counted'],
                    'ref' => ['Counted'],
                    'l2' => ['Counted'],
                ],
                'l1a' => ['Counted'],
                'l1b' => ['Counted'],
            ],
            'top' => ['Counted'],
        ]);

        self::assertSame($expected, $this->contentObject->render($conf));
        self::assertSame(9, $this->countingProcessor->calls);
    }

    public function testJsonFieldSkipsDataProcessingWhenIfFails(): void
    {
        $conf = ['fields.' => ['test' => 'JSON', 'test.' => ['if.' => ['isTrue' => 0], 'dataProcessing.' => ['10' => CountingDataProcessingExample::class, '10.' => ['as' => 'counter']]]]];

        self::assertSame(json_encode(['test' => '']), $this->contentObject->render($conf));
        self::assertSame(0, $this->countingProcessor->calls);
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
            [['fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['SomeCustomProcessing'])],
            [['dataProcessingMerge' => 1, 'fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['test' => '1', 'sites' => ['SomeCustomProcessing']])],
            [['dataProcessingMerge' => 1, 'fields.' => ['sites' => 'TEXT', 'sites.' => ['value' => 'fromFields']], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['sites' => ['SomeCustomProcessing']])],
            [['dataProcessingMerge' => 1, 'fields.' => ['test' => 'TEXT', 'test.' => ['value' => '1']], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], '20' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '20.' => ['as' => 'menu']]], json_encode(['test' => '1', 'sites' => ['SomeCustomProcessing'], 'menu' => ['SomeCustomProcessing']])],
            [['dataProcessingMerge' => 1, 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]], json_encode(['SomeCustomProcessing'])],
            [['fields.' => ['nested.' => ['dataProcessingMerge' => 1, 'fields.' => ['nestedTest' => 'INT', 'nestedTest.' => ['value' => 10]], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]], json_encode(['nested' => ['nestedTest' => 10, 'sites' => ['SomeCustomProcessing']]])],
            [['fields.' => ['nested.' => ['fields.' => ['nestedTest' => 'INT', 'nestedTest.' => ['value' => 10]], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]], json_encode(['nested' => ['SomeCustomProcessing']])],
            [['fields.' => ['test' => 'JSON', 'test.' => ['dataProcessingMerge' => 1, 'fields.' => ['inner' => 'TEXT', 'inner.' => ['value' => '1']], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites']]]]], json_encode(['test' => ['inner' => '1', 'sites' => ['SomeCustomProcessing']]])],
            [['nullableFieldsIfEmpty' => 'outer', 'fields.' => ['inner' => 'JSON', 'inner.' => ['fields.' => ['x' => 'TEXT', 'x.' => ['value' => '']]], 'outer' => 'TEXT', 'outer.' => ['value' => '']]], json_encode(['inner' => ['x' => ''], 'outer' => null])],
            [['fields.' => ['inner' => 'TEXT', 'inner.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'a']]]], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'b']]], json_encode(['SomeCustomProcessing'])],
            [['fields.' => ['test' => 'JSON', 'test.' => ['fields.' => ['inner' => 'TEXT', 'inner.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'a']]]], 'dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'b']]]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'nested']], 'as' => 'sites']]], json_encode(['SomeCustomProcessing'])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample']], json_encode(['SomeCustomProcessing'])],
            [['fields.' => ['test' => 'TEXT', 'test.' => ['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample']]]], json_encode(['test' => ['SomeCustomProcessing']])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\MultiVariableDataProcessingExample']], json_encode(['Second'])],
            [['dataProcessing.' => ['10' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\DataProcessingExample', '10.' => ['as' => 'sites'], '20' => 'FriendsOfTYPO3\Headless\Tests\Unit\ContentObject\MultiVariableDataProcessingExample']], json_encode(['SomeCustomProcessing'])],
        ];
    }

}
