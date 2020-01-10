<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional;

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

        $this->importDataSet(__DIR__ . '/Fixtures/pages.xml');

        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:headless/Configuration/TypoScript/constants.typoscript'],
                'setup' => ['EXT:headless/Configuration/TypoScript/setup.typoscript']
            ]
        );

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

    protected function checkDefaultContentFields($contentElement, $id, $pid, $type, $colPos = 0, $categories = '')
    {
        $this->assertEquals($id, $contentElement['id'], 'id mismatch');
        $this->assertEquals($pid, $contentElement['pid'], 'pid mismatch');
        $this->assertEquals($type, $contentElement['type'], 'type mismatch');
        $this->assertEquals($colPos, $contentElement['colPos'], 'colPos mismatch');
        $this->assertEquals($categories, $contentElement['categories'], 'categories mismatch');
    }

    protected function checkAppearanceFields($contentElement, $layout = 'default', $frameClass = 'default', $spaceBefore = '', $spaceAfter = '')
    {
        $contentElementAppearance = $contentElement['appearance'];

        $this->assertEquals($layout, $contentElementAppearance['layout'], 'layout mismatch');
        $this->assertEquals($frameClass, $contentElementAppearance['frameClass'], 'frameClass mismatch');
        $this->assertEquals($spaceBefore, $contentElementAppearance['spaceBefore'], 'spaceBefore mismatch');
        $this->assertEquals($spaceAfter, $contentElementAppearance['spaceAfter'], 'spaceAfter mismatch');
    }

    protected function checkHeaderFields($contentElement, $header = '', $subheader = '', $headerLayout = 0, $headerPosition= '')
    {
        $contentElementContent = $contentElement['content'];

        $this->assertEquals($header, $contentElementContent['header'], 'header mismatch');
        $this->assertEquals($subheader, $contentElementContent['subheader'], 'subheader mismatch');
        $this->assertEquals($headerLayout, $contentElementContent['headerLayout'], 'headerLayout mismatch');
        $this->assertEquals($headerPosition, $contentElementContent['headerPosition'], 'headerPosition mismatch');
        $this->assertTrue(isset($contentElementContent['headerLink']), 'headerLink not set');
    }

    protected function checkHeaderFieldsLink($contentElement, $link, $type, $urlPrefix, $target)
    {
        $contentElementHeaderFieldsLink = $contentElement['content']['headerLink'];

        $this->assertTrue(is_array($contentElementHeaderFieldsLink), 'headerLink not an array');
        $this->assertEquals($link, $contentElementHeaderFieldsLink['link'], 'link mismatch');
        $this->assertEquals($type, $contentElementHeaderFieldsLink['type'], 'type mismatch');
        $this->assertStringStartsWith($urlPrefix, $contentElementHeaderFieldsLink['url'], 'url mismatch');
        $this->assertEquals($target, $contentElementHeaderFieldsLink['target'], 'target mismatch');
    }
}
