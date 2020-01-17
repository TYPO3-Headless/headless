<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\PageTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class SchemaPageTypesTest extends BasePageTypesTest
{
    /**
     * @test
     */
    public function getMenu()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/?type=834')
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
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonSchema(
            (string)$response->getBody(),
            $this->getJsonSchemaPath() . 'page.json'
        );
    }
}
