<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ViewHelpers\Format\Json;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use FriendsOfTYPO3\Headless\ViewHelpers\Format\Json\DecodeViewHelper;

class DecodeViewHelperTest extends UnitTestCase
{
    public function testRender(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
        $decodeViewHelper = new DecodeViewHelper();
        $decodeViewHelper->setArguments(['json' => null]);
        $decodeViewHelper->setRenderChildrenClosure(function() { return "\n \n"; } );
        $result = $decodeViewHelper->render();
        self::assertSame(null, $result);
    }
}