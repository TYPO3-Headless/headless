<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\Redirects\Form\Element;

use FriendsOfTYPO3\Headless\Redirects\Form\Element\ShortUrlElement;
use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Renders the read-only short URL element through the real core parent
 * markup, so this breaks loudly if core changes the string the headless
 * override str_replace()s.
 */
class ShortUrlElementTest extends BaseHeadlessTesting
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

        $this->importCSVDataSet(__DIR__ . '/../../../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG'], $GLOBALS['BE_USER']);
        parent::tearDown();
    }

    public function testShortUrlIsRewrittenToFrontendBase(): void
    {
        $result = $this->renderFor('website.local');

        self::assertStringContainsString('https://front.local/s/Ab12Cd34', $result['html']);
        self::assertStringNotContainsString('https://website.local/s/Ab12Cd34', $result['html']);
    }

    public function testWildcardSourceHostResolvesViaSoleFullHeadlessSite(): void
    {
        $result = $this->renderFor('*');

        self::assertStringContainsString('https://front.local/s/Ab12Cd34', $result['html']);
    }

    /**
     * @return array<string, mixed>
     */
    private function renderFor(string $sourceHost): array
    {
        $request = new ServerRequest('https://backend.local/typo3/record/edit', 'GET');
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $element = $this->get(ShortUrlElement::class);
        $element->setData([
            'command' => 'edit',
            'tableName' => 'sys_redirect',
            'fieldName' => 'short_url',
            'databaseRow' => [
                'source_host' => $sourceHost,
                'source_path' => '/s/Ab12Cd34',
            ],
            'parameterArray' => [
                'itemFormElName' => 'data[sys_redirect][1][short_url]',
                'fieldConf' => [
                    'label' => 'Short URL',
                    'config' => ['readOnly' => true],
                ],
            ],
            'request' => $request,
        ]);

        return $element->render();
    }
}
