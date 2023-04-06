<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;

class HeadlessModeTest extends TestCase
{
    public function testMixedModeWithoutHeader(): void
    {
        $mode = new HeadlessMode();

        $request =  new ServerRequest();
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        $mode = $mode->withRequest($request);

        self::assertFalse($mode->isEnabled());
    }

    public function testMixedModeWithHeader(): void
    {
        $mode = new HeadlessMode();

        $request =  new ServerRequest();
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        $mode = $mode->withRequest($request);

        self::assertTrue($mode->isEnabled());
    }

    public function testDisabled(): void
    {
        $mode = new HeadlessMode();

        $request =  new ServerRequest();
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::NONE));

        $mode = $mode->withRequest($request);

        self::assertFalse($mode->isEnabled());
    }

    public function testNotSet(): void
    {
        $mode = new HeadlessMode();

        $request =  new ServerRequest();
        $request = $request->withHeader('Accept', 'application/json');

        $mode = $mode->withRequest($request);

        self::assertFalse($mode->isEnabled());
        // without passed request
        self::assertFalse((new HeadlessMode())->isEnabled());
    }

    public function testFullMode(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::FULL));

        $mode = $mode->withRequest($request);

        self::assertTrue($mode->isEnabled());
    }
}
