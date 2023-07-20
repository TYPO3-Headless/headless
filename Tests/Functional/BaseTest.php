<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional;

use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class BaseTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/headless',
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
                'setup' => ['EXT:headless/Configuration/TypoScript/setup.typoscript'],
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
    protected function assertJsonSchema(string $jsonString, string $jsonSchemaFile)
    {
        $data = json_decode($jsonString);

        $retriever = new UriRetriever();
        $schema = $retriever->retrieve(
            'file://' . $jsonSchemaFile
        );
        $refResolver = new SchemaStorage($retriever);
        $refResolver->resolveRef(
            $schema,
            'file://' . $jsonSchemaFile
        );
        $validator = new Validator();
        $validator->check($data, $schema);
        if ($validator->isValid() === false) {
            foreach ($validator->getErrors() as $error) {
                self::fail(sprintf('Property "%s" is not valid: %s in %s', $error['property'], $error['message'], $jsonString));
            }
        } else {
            self::assertTrue(true);
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
        self::assertEquals($id, $contentElement['id'], 'id mismatch');
        self::assertEquals($pid, $contentElement['pid'], 'pid mismatch');
        self::assertEquals($type, $contentElement['type'], 'type mismatch');
        self::assertEquals($colPos, $contentElement['colPos'], 'colPos mismatch');
        self::assertEquals($categories, $contentElement['categories'], 'categories mismatch');
    }

    protected function checkAppearanceFields($contentElement, $layout = 'default', $frameClass = 'default', $spaceBefore = '', $spaceAfter = '')
    {
        $contentElementAppearance = $contentElement['appearance'];

        self::assertEquals($layout, $contentElementAppearance['layout'], 'layout mismatch');
        self::assertEquals($frameClass, $contentElementAppearance['frameClass'], 'frameClass mismatch');
        self::assertEquals($spaceBefore, $contentElementAppearance['spaceBefore'], 'spaceBefore mismatch');
        self::assertEquals($spaceAfter, $contentElementAppearance['spaceAfter'], 'spaceAfter mismatch');
    }

    protected function checkHeaderFields($contentElement, $header = '', $subheader = '', $headerLayout = 0, $headerPosition= '')
    {
        $contentElementContent = $contentElement['content'];

        self::assertEquals($header, $contentElementContent['header'], 'header mismatch');
        self::assertEquals($subheader, $contentElementContent['subheader'], 'subheader mismatch');
        self::assertEquals($headerLayout, $contentElementContent['headerLayout'], 'headerLayout mismatch');
        self::assertEquals($headerPosition, $contentElementContent['headerPosition'], 'headerPosition mismatch');
        self::assertTrue(isset($contentElementContent['headerLink']), 'headerLink not set');
    }

    protected function checkHeaderFieldsLink($contentElement, $link, $urlPrefix, $target)
    {
        $contentElementHeaderFieldsLink = $contentElement['content']['headerLink'];

        self::assertIsArray($contentElementHeaderFieldsLink, 'headerLink not an array');
        self::assertEquals($link, $contentElementHeaderFieldsLink['linkText'], 'link mismatch');
        self::assertStringStartsWith($urlPrefix, $contentElementHeaderFieldsLink['href'], 'url mismatch');
        self::assertEquals($target, $contentElementHeaderFieldsLink['target'], 'target mismatch');
    }
}
