<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Redirects\Form\Element;

use FriendsOfTYPO3\Headless\Redirects\Form\Element\ShortUrlElement;
use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Form\NodeInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

class ShortUrlElementTest extends HeadlessUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageService;

        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('shallDisplayDebugInformation')->willReturn(false);
        $GLOBALS['BE_USER'] = $backendUser;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG'], $GLOBALS['BE_USER']);
        parent::tearDown();
    }

    public function testShortUrlIsReplacedWithResolvedFrontendUrl(): void
    {
        $request = $this->buildRequest();

        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->method('resolve')->with('api.tld', '/go', $request)->willReturn('https://frontend.tld/go');

        $element = $this->buildElement($urlResolver);
        $element->setData($this->buildData($request));

        $html = $element->render()['html'];

        self::assertStringContainsString('https://frontend.tld/go', $html);
        self::assertStringNotContainsString('https://api.tld/go', $html);
    }

    public function testShortUrlIsKeptWhenUrlCannotBeResolved(): void
    {
        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->method('resolve')->willReturn(null);

        $element = $this->buildElement($urlResolver);
        $element->setData($this->buildData($this->buildRequest()));

        self::assertStringContainsString('https://api.tld/go', $element->render()['html']);
    }

    public function testEditableViewWithoutRequestIsReturnedUnchanged(): void
    {
        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->expects(self::never())->method('resolve');

        $data = $this->buildData(null);
        $data['parameterArray']['fieldConf']['config']['readOnly'] = false;
        $data['processedTca'] = ['columns' => ['source_host' => ['config' => []], 'source_path' => ['config' => []]]];

        $element = $this->buildElement($urlResolver);
        $element->setData($data);

        self::assertStringContainsString('data[sys_redirect][1][source_host]', $element->render()['html']);
    }

    private function buildElement(SourceUrlResolver $urlResolver): ShortUrlElement
    {
        $icon = $this->createMock(Icon::class);
        $icon->method('render')->willReturn('<icon></icon>');

        $iconFactory = $this->createMock(IconFactory::class);
        $iconFactory->method('getIcon')->willReturn($icon);

        $fieldInformationNode = $this->createMock(NodeInterface::class);
        $fieldInformationNode->method('render')->willReturn([
            'additionalHiddenFields' => [],
            'additionalInlineLanguageLabelFiles' => [],
            'stylesheetFiles' => [],
            'javaScriptModules' => [],
            'inlineData' => [],
            'html' => '',
        ]);

        $nodeFactory = $this->createMock(NodeFactory::class);
        $nodeFactory->method('create')->willReturn($fieldInformationNode);

        $element = new ShortUrlElement($iconFactory, $urlResolver);
        $element->injectNodeFactory($nodeFactory);

        return $element;
    }

    private function buildRequest(): ServerRequest
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('isHttps')->willReturn(true);

        return (new ServerRequest())->withAttribute('normalizedParams', $normalizedParams);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(?ServerRequest $request): array
    {
        $data = [
            'fieldName' => 'short_url',
            'databaseRow' => ['source_host' => 'api.tld', 'source_path' => '/go'],
            'parameterArray' => [
                'fieldConf' => ['label' => 'Short URL', 'config' => ['readOnly' => true]],
                'itemFormElName' => 'data[sys_redirect][1][short_url]',
            ],
        ];

        if ($request !== null) {
            $data['request'] = $request;
        }

        return $data;
    }
}
