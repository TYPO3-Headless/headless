<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Hooks;

use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class HeadlessUserIntTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function processingOnPlainTextWithNewline()
    {
        $testProcessed = 'PlainText' . PHP_EOL . 'NextLine';
        $testContent = 'HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals(json_encode($testProcessed), '"' . $tsfe->content . '"');
    }

    /**
     * @test
     */
    public function processingOnQuotedText()
    {
        $testProcessed = '"PlainText' . PHP_EOL . 'NextLine"';
        $testContent = 'HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals(json_encode($testProcessed), '"' . $tsfe->content . '"');
    }

    /**
     * @test
     */
    public function processingOnQuotedContent()
    {
        $testProcessed = '"PlainText' . PHP_EOL . 'NextLine"';
        $testContent = '"HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END"';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals(json_encode($testProcessed), $tsfe->content);
    }

    /**
     * @test
     */
    public function processingOnQuotedJsonContent()
    {
        $testProcessed = json_encode(
            [
                'key1' => 'value',
            ]
        );
        $testContent = '"HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END"';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals($testProcessed, $tsfe->content);
    }

    /**
     * @test
     */
    public function processingEmptyPluginResponse()
    {
        $testProcessed = json_encode(
            ''
        );
        $testContent = '"HEADLESS_INT_NULL_START<<' . $testProcessed . '>>HEADLESS_INT_NULL_END"';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals(json_encode(null), $tsfe->content);

        $testProcessed = json_encode(
            ''
        );
        $testContent = '"NESTED_HEADLESS_INT_NULL_START<<' . $testProcessed . '>>NESTED_HEADLESS_INT_NULL_END"';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals(json_encode(null), $tsfe->content);
    }

    /**
     * @test
     */
    public function processingOnNestedJsonContent()
    {
        $nestedProcessed = json_encode(
            [
                'key2' => 'value2',
            ]
        );

        $testProcessed = str_replace(
            '[nested]',
            'NESTED_HEADLESS_INT_START<<' . $nestedProcessed . '>>NESTED_HEADLESS_INT_END',
            json_encode(
                [
                    'key1' => 'value',
                    'nestedContent' => '[nested]',
                ]
            )
        );

        $finalOutput = json_encode(
            [
                'key1' => 'value',
                'nestedContent' => [
                    'key2' => 'value2',
                ],
            ]
        );

        $testContent = '"HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END"';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals($finalOutput, $tsfe->content);
    }

    /**
     * @test
     */
    public function processingOnMultipleUserIntOnPageJsonContent()
    {
        $nestedProcessed = json_encode(
            [
                'key2' => 'value2',
            ]
        );

        $testProcessed2 = json_encode(
            [
                'key3' => 'value3',
            ]
        );

        $testProcessed = str_replace(
            '[nested]',
            'NESTED_HEADLESS_INT_START<<' . $nestedProcessed . '>>NESTED_HEADLESS_INT_END',
            json_encode(
                [
                    'key1' => 'value',
                    'nestedContent' => '[nested]',
                ]
            )
        );

        $finalOutput = json_encode(
            [
                [
                    'key1' => 'value',
                    'nestedContent' => [
                        'key2' => 'value2',
                    ],
                ],
                [
                    'key3' => 'value3',
                ],
            ]
        );

        $testContent = '["HEADLESS_INT_START<<' . $testProcessed . '>>HEADLESS_INT_END","HEADLESS_INT_START<<' . $testProcessed2 . '>>HEADLESS_INT_END"]';

        $setup = [];
        $setup['plugin.']['tx_headless.']['staticTemplate'] = '1';

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup = $setup;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();

        $tsfe->content = $testContent;

        $classUnderTest = new HeadlessUserInt();

        $tsfe->content = $classUnderTest->unwrap($tsfe->content);

        self::assertEquals($finalOutput, $tsfe->content);
    }

    /**
     * @test
     */
    public function wrapTest()
    {
        $headlessUserInt = new HeadlessUserInt();

        $genericUserIntScriptTag = '<!--INT_SCRIPT.d53df2a300e62171a7b4882c4b88a153-->';
        $expectedOutput = HeadlessUserInt::STANDARD . '_START<<' . $genericUserIntScriptTag . '>>' . HeadlessUserInt::STANDARD . '_END';
        self::assertSame($expectedOutput, $headlessUserInt->wrap($genericUserIntScriptTag));

        $testString = '12345test12345test12345test12345test12345test';
        self::assertSame($testString, $headlessUserInt->wrap($testString));

        $expectedOutput = HeadlessUserInt::NESTED . '_START<<' . $genericUserIntScriptTag . '>>' . HeadlessUserInt::NESTED . '_END';
        self::assertSame($expectedOutput, $headlessUserInt->wrap($genericUserIntScriptTag, HeadlessUserInt::NESTED));

        self::assertSame(
            strtoupper($genericUserIntScriptTag),
            $headlessUserInt->wrap(strtoupper($genericUserIntScriptTag))
        );
    }
}
