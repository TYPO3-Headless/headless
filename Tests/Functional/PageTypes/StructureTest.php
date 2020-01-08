<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Test\Functional\PageTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class StructureTest extends BaseTest
{
    /**
     * @test
     */
    public function getMenuStructure()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/?type=834')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $pageTree = json_decode((string)$response->getBody(), true);

        $this->assertTrue(isset($pageTree['navigation']));
        $this->assertEquals(1, count($pageTree['navigation']));
        $this->assertTrue(isset($pageTree['navigation'][0]));
        $this->assertEquals(2, count($pageTree['navigation'][0]['children']));
        $this->assertEquals(1, count($pageTree['navigation'][0]['children'][0]['children']));
        $this->assertEquals(0, count($pageTree['navigation'][0]['children'][1]['children']));

        $this->assertEquals('/', $pageTree['navigation'][0]['link']);
        $this->assertEquals('/page1', $pageTree['navigation'][0]['children'][0]['link']);
        $this->assertEquals('/page1/page1_1', $pageTree['navigation'][0]['children'][0]['children'][0]['link']);
        $this->assertEquals('/page2', $pageTree['navigation'][0]['children'][1]['link']);
    }
}
