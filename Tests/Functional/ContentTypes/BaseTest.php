<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\ContentTypes;

use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class BaseTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/headless'
    ];

    /**
     * set up objects
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/../Fixtures/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/content.xml');

        $this->setUpFrontendRootPage(1, ['EXT:headless/Configuration/TypoScript/setup.typoscript']);

        $siteConfigDir = Environment::getConfigPath() . '/sites/headless';

        mkdir($siteConfigDir, 0777, true);

        file_put_contents($siteConfigDir . '/config.yaml', "rootPageId: 1\nbase: /\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n");
    }

    /**
     * @param string $jsonString
     * @param string $jsonSchemaFile
     */
    protected function assertJsonSchema($jsonString, $jsonSchemaFile)
    {
        $data = json_decode($jsonString);

        $retriever = new UriRetriever();
        $schema = $retriever->retrieve(
            'file://' . $jsonSchemaFile
        );
        $refResolver = new RefResolver($retriever);
        $refResolver->resolve(
            $schema,
            'file://' . $jsonSchemaFile
        );
        $validator = new Validator();
        $validator->check($data, $schema);
        if (false === $validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $this->fail(sprintf('Property "%s" is not valid: %s in %s', $error['property'], $error['message'], $jsonString));
            }
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Defines the path where the json schema files are located.
     *
     * @return string
     */
    public function getJsonSchemaPath()
    {
        $extensionPath = ExtensionManagementUtility::extPath('headless');
        return $extensionPath . '/Tests/Functional/json-schema/';
    }

    protected function checkDefaultContentFields($contentElement, $id, $pid, $type, $colPos, $categories = '')
    {
        $this->assertEquals($id, $contentElement['id']);
        $this->assertEquals($pid, $contentElement['pid']);
        $this->assertEquals($type, $contentElement['type']);
        $this->assertEquals($colPos, $contentElement['colPos']);
        $this->assertEquals($categories, $contentElement['categories']);
    }

    protected function checkAppearanceFields($contentElement, $layout, $frameClass, $spaceBefore, $spaceAfter)
    {
        $contentElementAppearance = $contentElement['appearance'];

        $this->assertEquals($layout, $contentElementAppearance['layout']);
        $this->assertEquals($frameClass, $contentElementAppearance['frameClass']);
        $this->assertEquals($spaceBefore, $contentElementAppearance['spaceBefore']);
        $this->assertEquals($spaceAfter, $contentElementAppearance['spaceAfter']);
    }

    protected function checkHeaderFields($contentElement, $header, $subheader, $headerLayout, $headerPosition)
    {
        $contentElementContent = $contentElement['content'];

        $this->assertEquals($header, $contentElementContent['header']);
        $this->assertEquals($subheader, $contentElementContent['subheader']);
        $this->assertEquals($headerLayout, $contentElementContent['headerLayout']);
        $this->assertEquals($headerPosition, $contentElementContent['headerPosition']);
        $this->assertTrue(isset($contentElementContent['headerLink']));
    }

    protected function checkHeaderFieldsLink($contentElement, $link, $type, $urlPrefix, $target)
    {
        $contentElementHeaderFieldsLink = $contentElement['content']['headerLink'];

        $this->assertTrue(is_array($contentElementHeaderFieldsLink));
        $this->assertEquals($link, $contentElementHeaderFieldsLink['link']);
        $this->assertEquals($type, $contentElementHeaderFieldsLink['type']);
        $this->assertStringStartsWith($urlPrefix, $contentElementHeaderFieldsLink['url']);
        $this->assertEquals($target, $contentElementHeaderFieldsLink['target']);
    }
}
