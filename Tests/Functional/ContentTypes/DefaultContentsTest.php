<?php

declare(strict_types=1);

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class DefaultContentsTest extends \FriendsOfTYPO3\Headless\Test\Functional\ContentTypes\BaseTest
{
    public function testContentStructure()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);
        $contentTree = $fullTree['content'];

        $this->assertTrue(isset($contentTree['colPos0']));
        $this->assertTrue(count($contentTree['colPos0']) > 0);
        $this->assertTrue(isset($contentTree['colPos0'][0]['appearance']));
        $this->assertTrue(is_array($contentTree['colPos0'][0]['appearance']));
        $this->assertTrue(isset($contentTree['colPos1']));
        $this->assertTrue(count($contentTree['colPos1']) > 0);
    }
}
