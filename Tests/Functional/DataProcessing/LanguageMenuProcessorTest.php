<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\DataProcessing;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * LanguageMenuProcessor feeds the `i18n.languages` section of every page
 * response (lib.i18n, alias "headless-language-menu"). Rendered here with a
 * two-language site and a translated root page.
 */
class LanguageMenuProcessorTest extends BaseHeadlessTesting
{
    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/translated_pages.csv');

        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            <<<YAML
            rootPageId: 1
            base: https://website.local/
            headless: 1
            frontendBase: https://front.local
            baseVariants: { }
            routes: { }
            languages:
              -
                title: English
                enabled: true
                languageId: 0
                base: /
                locale: en_US.UTF-8
                navigationTitle: English
                flag: us
              -
                title: Deutsch
                enabled: true
                languageId: 1
                base: /de/
                locale: de_DE.UTF-8
                navigationTitle: Deutsch
                flag: de
            YAML
        );
    }

    public function testLanguageMenuIsRenderedIntoI18nSection(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertSame(200, $response->getStatusCode());

        $fullTree = json_decode((string)$response->getBody(), true);

        $languages = $fullTree['i18n'] ?? null;
        self::assertIsArray($languages, 'i18n missing in page response');
        self::assertCount(2, $languages);

        $english = $languages[0];
        self::assertSame(0, $english['languageId']);
        self::assertSame('English', $english['navigationTitle']);
        self::assertSame(1, $english['active']);
        self::assertSame(1, $english['available']);

        $german = $languages[1];
        self::assertSame(1, $german['languageId']);
        self::assertSame('Deutsch', $german['navigationTitle']);
        self::assertSame(0, $german['active']);
        self::assertSame(1, $german['available'], 'root page is translated, so DE must be available');
        self::assertStringContainsString('/de/', $german['link']);
    }
}
