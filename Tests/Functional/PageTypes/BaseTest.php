<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\PageTypes;

use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class BaseTest extends FunctionalTestCase
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

    /**
     * @test
     */
    public function getMenu()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/?id=1&type=834')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonSchema(
            (string)$response->getBody(),
            $this->getJsonSchemaPath() . 'menu.json'
        );
    }

    /**
     * @test
     */
    public function getPage()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/?id=1')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonSchema(
            (string)$response->getBody(),
            $this->getJsonSchemaPath() . 'page.json'
        );
    }
}
