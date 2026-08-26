<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Utility;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;

use function json_encode;
use function str_repeat;
use function str_replace;
use function strtoupper;

class HeadlessUserIntTest extends HeadlessUnitTestCase
{
    private HeadlessUserInt $headlessUserInt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->headlessUserInt = new HeadlessUserInt();
    }

    public function testWrapReplacesIntScriptMarkers(): void
    {
        $marker = '<!--INT_SCRIPT.' . str_repeat('a1', 16) . '-->';

        self::assertSame(
            'preHEADLESS_INT_START<<' . $marker . '>>HEADLESS_INT_ENDpost',
            $this->headlessUserInt->wrap('pre' . $marker . 'post')
        );
    }

    public function testWrapWithCustomType(): void
    {
        $marker = '<!--INT_SCRIPT.' . str_repeat('b2', 16) . '-->';

        self::assertSame(
            'NESTED_HEADLESS_INT_START<<' . $marker . '>>NESTED_HEADLESS_INT_END',
            $this->headlessUserInt->wrap($marker, HeadlessUserInt::NESTED)
        );
    }

    public function testWrapLeavesContentWithoutMarkersUntouched(): void
    {
        self::assertSame('{"foo":"bar"}', $this->headlessUserInt->wrap('{"foo":"bar"}'));
    }

    public function testHasNonCacheableContent(): void
    {
        self::assertTrue($this->headlessUserInt->hasNonCacheableContent('x HEADLESS_INT_START<<y>>HEADLESS_INT_END z'));
        self::assertTrue($this->headlessUserInt->hasNonCacheableContent('NESTED_HEADLESS_INT_START<<y>>NESTED_HEADLESS_INT_END'));
        self::assertFalse($this->headlessUserInt->hasNonCacheableContent('{"foo":"bar"}'));
    }

    public function testUnwrapQuotedJsonContentIsInlinedRaw(): void
    {
        self::assertSame(
            '{"nested":{"a":1}}',
            $this->headlessUserInt->unwrap('{"nested":"HEADLESS_INT_START<<{"a":1}>>HEADLESS_INT_END"}')
        );
    }

    public function testUnwrapQuotedPlainStringIsJsonEncoded(): void
    {
        self::assertSame(
            '{"v":"plain text"}',
            $this->headlessUserInt->unwrap('{"v":"HEADLESS_INT_START<<plain text>>HEADLESS_INT_END"}')
        );
    }

    public function testUnwrapQuotedScalarJsonStaysRaw(): void
    {
        self::assertSame(
            '{"v":123}',
            $this->headlessUserInt->unwrap('{"v":"HEADLESS_INT_START<<123>>HEADLESS_INT_END"}')
        );
    }

    public function testUnwrapNullableTypeWithEmptyContentBecomesNull(): void
    {
        self::assertSame(
            '{"v":null}',
            $this->headlessUserInt->unwrap('{"v":"HEADLESS_INT_NULL_START<<>>HEADLESS_INT_NULL_END"}')
        );
    }

    public function testUnwrapUnquotedPlainContentIsEscapedForJsonContext(): void
    {
        self::assertSame(
            'a Hello \"W\" b',
            $this->headlessUserInt->unwrap('a HEADLESS_INT_START<<Hello "W">>HEADLESS_INT_END b')
        );
    }

    public function testUnwrapNestedType(): void
    {
        self::assertSame(
            '{"outer":{"n":2}}',
            $this->headlessUserInt->unwrap('{"outer":"NESTED_HEADLESS_INT_START<<{"n":2}>>NESTED_HEADLESS_INT_END"}')
        );
    }

    public function testUnwrapQuotedContentWithInvalidUtf8BecomesNull(): void
    {
        self::assertSame(
            '{"v":null}',
            $this->headlessUserInt->unwrap('{"v":"HEADLESS_INT_START<<' . "\xB1" . '>>HEADLESS_INT_END"}')
        );
    }

    public function testUnwrapUnquotedContentWithInvalidUtf8IsRemoved(): void
    {
        self::assertSame(
            'a  b',
            $this->headlessUserInt->unwrap('a HEADLESS_INT_START<<' . "\xB1" . '>>HEADLESS_INT_END b')
        );
    }

    public function testUnwrapLeavesContentWithoutMarkersUntouched(): void
    {
        self::assertSame('{"foo":"bar"}', $this->headlessUserInt->unwrap('{"foo":"bar"}'));
    }

    public function testWrapLeavesUppercasedMarkerUntouched(): void
    {
        $uppercasedMarker = strtoupper('<!--INT_SCRIPT.' . str_repeat('a1', 16) . '-->');

        self::assertSame($uppercasedMarker, $this->headlessUserInt->wrap($uppercasedMarker));
    }

    public function testUnwrapUnquotedMultilineContentIsEscaped(): void
    {
        $plugin = 'PlainText' . PHP_EOL . 'NextLine';

        $content = $this->headlessUserInt->unwrap('HEADLESS_INT_START<<' . $plugin . '>>HEADLESS_INT_END');

        self::assertSame(json_encode($plugin), '"' . $content . '"');
    }

    public function testUnwrapQuotedMultilineContentIsJsonEncoded(): void
    {
        $plugin = '"PlainText' . PHP_EOL . 'NextLine"';

        $content = $this->headlessUserInt->unwrap('"HEADLESS_INT_START<<' . $plugin . '>>HEADLESS_INT_END"');

        self::assertSame(json_encode($plugin), $content);
    }

    public function testUnwrapUnquotedWrapperWithQuotedMultilineContentIsEscaped(): void
    {
        $plugin = '"PlainText' . PHP_EOL . 'NextLine"';

        $content = $this->headlessUserInt->unwrap('HEADLESS_INT_START<<' . $plugin . '>>HEADLESS_INT_END');

        self::assertSame(json_encode($plugin), '"' . $content . '"');
    }

    public function testUnwrapQuotedContentWithInnerQuotesIsJsonEncoded(): void
    {
        $plugin = 'plain"with"quotes';

        $content = $this->headlessUserInt->unwrap('"HEADLESS_INT_START<<' . $plugin . '>>HEADLESS_INT_END"');

        self::assertSame(json_encode($plugin), $content);
    }

    public function testUnwrapNullableTypesWithEmptyEncodedStringBecomeNull(): void
    {
        $emptyResponse = json_encode('');

        self::assertSame(
            'null',
            $this->headlessUserInt->unwrap('"HEADLESS_INT_NULL_START<<' . $emptyResponse . '>>HEADLESS_INT_NULL_END"')
        );
        self::assertSame(
            'null',
            $this->headlessUserInt->unwrap('"NESTED_HEADLESS_INT_NULL_START<<' . $emptyResponse . '>>NESTED_HEADLESS_INT_NULL_END"')
        );
    }

    public function testUnwrapNestedMarkerInsideOuterUserIntContent(): void
    {
        $nested = json_encode(['key2' => 'value2']);
        $outer = str_replace(
            '[nested]',
            'NESTED_HEADLESS_INT_START<<' . $nested . '>>NESTED_HEADLESS_INT_END',
            json_encode(['key1' => 'value', 'nestedContent' => '[nested]'])
        );

        self::assertSame(
            json_encode(['key1' => 'value', 'nestedContent' => ['key2' => 'value2']]),
            $this->headlessUserInt->unwrap('"HEADLESS_INT_START<<' . $outer . '>>HEADLESS_INT_END"')
        );
    }

    public function testUnwrapMultipleUserIntMarkersInOnePayload(): void
    {
        $first = json_encode(['key1' => 'value']);
        $second = json_encode(['key3' => 'value3']);

        self::assertSame(
            json_encode([['key1' => 'value'], ['key3' => 'value3']]),
            $this->headlessUserInt->unwrap(
                '["HEADLESS_INT_START<<' . $first . '>>HEADLESS_INT_END","HEADLESS_INT_START<<' . $second . '>>HEADLESS_INT_END"]'
            )
        );
    }
}
