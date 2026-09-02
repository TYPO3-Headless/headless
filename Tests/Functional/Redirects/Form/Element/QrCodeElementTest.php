<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\Redirects\Form\Element;

use FriendsOfTYPO3\Headless\Redirects\Form\Element\QrCodeElement;
use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Renders the element through the real core parent markup, so this breaks
 * loudly if core changes the string the headless override str_replace()s.
 */
class QrCodeElementTest extends BaseHeadlessTesting
{
    protected array $coreExtensionsToLoad = [
        'install',
        'redirects',
    ];

    public function setUp(): void
    {
        parent::setUp();

        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: https://website.local/\nheadless: 1\nfrontendBase: https://front.local\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
    }

    public function testQrCodeContentIsRewrittenToFrontendBase(): void
    {
        $result = $this->renderFor('website.local');

        self::assertStringContainsString('content="https://front.local/qr-source"', $result['html']);
        self::assertStringNotContainsString('website.local', $result['html']);
    }

    public function testWildcardSourceHostResolvesViaSoleFullHeadlessSite(): void
    {
        $result = $this->renderFor('*');

        self::assertStringContainsString('content="https://front.local/qr-source"', $result['html']);
    }

    public function testSourceHostWithoutMatchingSiteKeepsCoreMarkup(): void
    {
        $result = $this->renderFor('unknown.tld');

        self::assertStringContainsString('content="https://unknown.tld/qr-source"', $result['html']);
    }

    /**
     * @return array<string, mixed>
     */
    private function renderFor(string $sourceHost): array
    {
        $element = $this->get(QrCodeElement::class);
        $element->setData([
            'command' => 'edit',
            'tableName' => 'sys_redirect',
            'fieldName' => 'qr_code',
            'databaseRow' => [
                'source_host' => $sourceHost,
                'source_path' => '/qr-source',
            ],
            'parameterArray' => [
                'fieldConf' => ['config' => []],
            ],
            'request' => new ServerRequest(),
        ]);

        return $element->render();
    }
}
