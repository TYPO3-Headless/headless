<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\PageTypes;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseTest;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

abstract class BasePageTypesTest extends BaseTest
{
    /**
     * @param string $jsonString
     * @param string $jsonSchemaFile
     */
    protected function assertJsonSchema(string $jsonString, string $jsonSchemaFile)
    {
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
    public function getJsonSchemaPath(): string
    {
        $extensionPath = ExtensionManagementUtility::extPath('headless');
        return $extensionPath . 'Tests/Functional/json-schema/';
    }
}
