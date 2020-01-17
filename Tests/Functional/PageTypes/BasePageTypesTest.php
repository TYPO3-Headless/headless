<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\PageTypes;

use FriendsOfTYPO3\Headless\Test\Functional\BaseTest;
use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

abstract class BasePageTypesTest extends BaseTest
{
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
}
