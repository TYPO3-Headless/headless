<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Unit\Hooks;

use FriendsOfTYPO3\Headless\Hooks\IntScriptEncoderHook;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class IntScriptEncoderHookTest extends UnitTestCase
{
    /**
     * @test
     */
    public function processingOnForeignPageType()
    {
        $testPageType = 999;
        $testContent = 'HEADLESS_JSON_START<<PlainText>>HEADLESS_JSON_END';

        $setup_constants = [];
        $setup_constants['config.']['headless.']['type.']['headless'] = $testPageType;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup_constants = $setup_constants;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();
        $tsfe->type = $testPageType+1;

        $tsfe->content = $testContent;

        $classUnderTest = new IntScriptEncoderHook();

        $classUnderTest->performExtraJsonEncoding([], $tsfe->reveal());

        $this->assertEquals($testContent, $tsfe->content);
    }

    /**
     * @test
     */
    public function processingOnPlainTextWithNewline()
    {
        $testPageType = 999;
        $testProcessed = 'PlainText' . PHP_EOL . 'NextLine';
        $testContent = 'HEADLESS_JSON_START<<' . $testProcessed . '>>HEADLESS_JSON_END';

        $setup_constants = [];
        $setup_constants['config.']['headless.']['type.']['headless'] = $testPageType;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup_constants = $setup_constants;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();
        $tsfe->type = $testPageType;

        $tsfe->content = $testContent;

        $classUnderTest = new IntScriptEncoderHook();

        $classUnderTest->performExtraJsonEncoding([], $tsfe->reveal());

        $this->assertEquals(json_encode($testProcessed), '"' . $tsfe->content . '"');
    }

    /**
     * @test
     */
    public function processingOnQuotedText()
    {
        $testPageType = 999;
        $testProcessed = '"PlainText' . PHP_EOL . 'NextLine"';
        $testContent = 'HEADLESS_JSON_START<<' . $testProcessed . '>>HEADLESS_JSON_END';

        $setup_constants = [];
        $setup_constants['config.']['headless.']['type.']['headless'] = $testPageType;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup_constants = $setup_constants;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();
        $tsfe->type = $testPageType;

        $tsfe->content = $testContent;

        $classUnderTest = new IntScriptEncoderHook();

        $classUnderTest->performExtraJsonEncoding([], $tsfe->reveal());

        $this->assertEquals(json_encode($testProcessed), '"' . $tsfe->content . '"');
    }

    /**
     * @test
     */
    public function processingOnQuotedContent()
    {
        $testPageType = 999;
        $testProcessed = '"PlainText' . PHP_EOL . 'NextLine"';
        $testContent = '"HEADLESS_JSON_START<<' . $testProcessed . '>>HEADLESS_JSON_END"';

        $setup_constants = [];
        $setup_constants['config.']['headless.']['type.']['headless'] = $testPageType;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup_constants = $setup_constants;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();
        $tsfe->type = $testPageType;

        $tsfe->content = $testContent;

        $classUnderTest = new IntScriptEncoderHook();

        $classUnderTest->performExtraJsonEncoding([], $tsfe->reveal());

        $this->assertEquals(json_encode($testProcessed), $tsfe->content);
    }

    /**
     * @test
     */
    public function processingOnQuotedJsonContent()
    {
        $testPageType = 999;
        $testProcessed = json_encode([
            'key1' => 'value'
        ]);
        $testContent = '"HEADLESS_JSON_START<<' . $testProcessed . '>>HEADLESS_JSON_END"';

        $setup_constants = [];
        $setup_constants['config.']['headless.']['type.']['headless'] = $testPageType;

        $tmpl = $this->prophesize(TemplateService::class);
        $tmpl->setup_constants = $setup_constants;

        $tsfe = $this->prophesize(TypoScriptFrontendController::class);
        $tsfe->tmpl = $tmpl->reveal();
        $tsfe->type = $testPageType;

        $tsfe->content = $testContent;

        $classUnderTest = new IntScriptEncoderHook();

        $classUnderTest->performExtraJsonEncoding([], $tsfe->reveal());

        $this->assertEquals($testProcessed, $tsfe->content);
    }
}
