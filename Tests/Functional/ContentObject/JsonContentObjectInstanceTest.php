<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\ContentObject;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class JsonContentObjectInstanceTest extends BaseHeadlessTesting
{
    public function testEveryRenderGetsItsOwnInstance(): void
    {
        $factory = $this->get(ContentObjectFactory::class);
        $request = new ServerRequest();
        $renderer = $this->get(ContentObjectRenderer::class);

        $first = $factory->getContentObject('JSON', $request, $renderer);
        $second = $factory->getContentObject('JSON', $request, $renderer);

        self::assertNotSame($first, $second);
    }
}
