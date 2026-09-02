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
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use LogicException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class HeadlessModeTest extends UnitTestCase
{
    public function testMixedModeWithoutHeader(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        $mode = $mode->withRequest($request);

        self::assertFalse($mode->isEnabled());
    }

    public function testMixedModeWithHeader(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        $mode = $mode->withRequest($request);

        self::assertTrue($mode->isEnabled());
    }

    public function testDisabled(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withAttribute('headless', new Headless(HeadlessMode::NONE));

        $mode = $mode->withRequest($request);

        self::assertFalse($mode->isEnabled());
    }

    public function testNotSet(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();
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

    public function testWithRequestReturnsCloneAndDoesNotLeakToOriginal(): void
    {
        $base = new HeadlessMode();

        $a = $base->withRequest(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL))
        );
        $b = $a->withRequest(
            (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::NONE))
        );

        self::assertNotSame($base, $a, 'withRequest must return a fresh instance');
        self::assertNotSame($a, $b, 'each withRequest call must return a fresh instance');
        self::assertTrue($a->isEnabled(), 'first clone keeps its own request');
        self::assertFalse($b->isEnabled(), 'second clone has its own request');
        self::assertFalse($base->isEnabled(), 'untouched original is still requestless');
    }

    public function testMixedModeRejectsCompositeAcceptHeader(): void
    {
        $request = (new ServerRequest())
            ->withHeader('Accept', 'application/json, text/plain, */*')
            ->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        self::assertFalse(
            (new HeadlessMode())->withRequest($request)->isEnabled(),
            'MIXED mode must only react to an Accept header that is exactly application/json'
        );
    }

    public function testMixedModeAcceptsStrictApplicationJsonHeader(): void
    {
        $request = (new ServerRequest())
            ->withHeader('Accept', 'application/json')
            ->withAttribute('headless', new Headless(HeadlessMode::MIXED));

        self::assertTrue((new HeadlessMode())->withRequest($request)->isEnabled());
    }

    public function testIsEnabledForDoesNotBindRequestToInstance(): void
    {
        $mode = new HeadlessMode();
        $request = (new ServerRequest())->withAttribute('headless', new Headless(HeadlessMode::FULL));

        self::assertTrue($mode->isEnabledFor($request), 'pure check returns true for FULL request');
        self::assertFalse($mode->isEnabled(), 'isEnabledFor must not bind the request to the instance');
    }

    public function testIsEnabledForMirrorsWithRequestIsEnabled(): void
    {
        $mode = new HeadlessMode();

        foreach ([HeadlessMode::NONE => false, HeadlessMode::FULL => true] as $modeValue => $expected) {
            $request = (new ServerRequest())->withAttribute('headless', new Headless($modeValue));
            self::assertSame($expected, $mode->isEnabledFor($request));
            self::assertSame($expected, $mode->withRequest($request)->isEnabled());
        }
    }

    public function testOverrideBackendRequestThrowsWithoutPriorWithRequest(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionCode(1747200000);

        (new HeadlessMode())->overrideBackendRequestBySite(
            new Site('test', 1, ['headless' => HeadlessModeInterface::FULL])
        );
    }

    public function testBackendRequestOverride(): void
    {
        $mode = new HeadlessMode();

        $request = new ServerRequest();

        $mode = $mode->withRequest($request);

        self::assertNull($request->getAttribute('headless'));

        $request = $mode->overrideBackendRequestBySite(new Site('test', 1, ['headless' => HeadlessModeInterface::FULL]));

        self::assertSame(HeadlessModeInterface::FULL, $request->getAttribute('headless')->getMode());

        $request = $mode->overrideBackendRequestBySite(new Site('test', 1, ['headless' => HeadlessModeInterface::MIXED]));

        self::assertSame(HeadlessModeInterface::NONE, $request->getAttribute('headless')->getMode());

        $request = $mode->overrideBackendRequestBySite(
            new Site('test', 1, ['headless' => HeadlessModeInterface::MIXED]),
            new SiteLanguage(
                1,
                'en_US',
                new Uri('/'),
                []
            )
        );

        self::assertSame(HeadlessModeInterface::NONE, $request->getAttribute('headless')->getMode());
    }
}
