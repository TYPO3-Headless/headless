<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ViewHelpers;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\ViewHelpers\LoginFormViewHelper;
use LogicException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

class LoginFormViewHelperTest extends HeadlessUnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private ViewHelperVariableContainer $viewHelperVariableContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'headless-unit-test-encryption-key';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        parent::tearDown();
    }

    public function testRenderThrowsOutsideExtbaseContext(): void
    {
        $viewHelper = new LoginFormViewHelper();

        $renderingContext = $this->createMock(RenderingContext::class);
        $renderingContext->method('getViewHelperVariableContainer')->willReturn(new ViewHelperVariableContainer());
        $renderingContext->method('hasAttribute')->willReturn(false);

        $viewHelper->setRenderingContext($renderingContext);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1639821904);

        $viewHelper->render();
    }

    public function testRenderCollectsFieldsReferrerAndTrustedProperties(): void
    {
        $viewHelper = $this->buildViewHelper(['actionUri' => '/login', 'name' => 'login', 'novalidate' => true]);
        $viewHelper->setRenderChildrenClosure(static fn(): array => [
            ['name' => 'username', 'type' => 'text', 'value' => ''],
        ]);

        $data = json_decode($viewHelper->render(), true);
        $fields = array_column($data, 'value', 'name');

        self::assertSame('', $fields['username'] ?? null);
        self::assertSame('Felogin', $fields['tx_felogin_login[__referrer][@extension]']);
        self::assertSame('Login', $fields['tx_felogin_login[__referrer][@controller]']);
        self::assertSame('login', $fields['tx_felogin_login[__referrer][@action]']);
        self::assertStringStartsWith(
            base64_encode(serialize([])),
            $fields['tx_felogin_login[__referrer][arguments]']
        );
        self::assertStringStartsWith(
            '{"@extension":"Felogin","@controller":"Login","@action":"login"}',
            $fields['tx_felogin_login[__referrer][@request]']
        );
        self::assertSame('trusted-token', $fields['tx_felogin_login[__trustedProperties]']);
    }

    public function testRenderWithGetMethodSetsMethodAttribute(): void
    {
        $viewHelper = $this->buildViewHelper(['actionUri' => '/login', 'method' => 'GET']);
        $viewHelper->setRenderChildrenClosure(static fn(): array => []);

        $viewHelper->render();

        $tag = (new ReflectionProperty($viewHelper, 'tag'))->getValue($viewHelper);

        self::assertSame('get', $tag->getAttribute('method'));
    }

    public function testRenderThrowsWhenRequestTokenIsCombinedWithGetMethod(): void
    {
        $viewHelper = $this->buildViewHelper(['actionUri' => '/login', 'method' => 'get', 'requestToken' => 1]);
        $viewHelper->setRenderChildrenClosure(static fn(): array => []);

        $this->expectException(LogicException::class);
        $this->expectExceptionCode(1651775963);

        $viewHelper->render();
    }

    public function testRenderAddsSignedRequestTokenField(): void
    {
        $viewHelper = $this->buildViewHelper(['actionUri' => '/login', 'requestToken' => '@nonce']);
        $viewHelper->setRenderChildrenClosure(static fn(): array => []);

        $data = json_decode($viewHelper->render(), true);
        $fields = array_column($data, 'value', 'name');

        self::assertArrayHasKey(RequestToken::PARAM_NAME, $fields);
        self::assertCount(3, explode('.', $fields[RequestToken::PARAM_NAME]), 'value must be a JWT');
    }

    public function testRenderRequestTokenWithUnknownSigningTypeThrows(): void
    {
        $viewHelper = $this->buildViewHelper([
            'actionUri' => '/login',
            'requestToken' => 'my-scope',
            'signingType' => 'unknown',
        ]);
        $viewHelper->setRenderChildrenClosure(static fn(): array => []);

        $this->expectException(LogicException::class);
        $this->expectExceptionCode(1664260307);

        $viewHelper->render();
    }

    public function testAdditionalIdentityPropertiesAreParsedIntoHiddenFields(): void
    {
        $viewHelper = $this->buildViewHelper(['actionUri' => '/login']);
        $viewHelper->setRenderChildrenClosure(function () {
            $this->viewHelperVariableContainer->add(
                FormViewHelper::class,
                'additionalIdentityProperties',
                ['<input type="hidden" name="tx[item][__identity]" value="9" />']
            );
            return [];
        });

        $data = json_decode($viewHelper->render(), true);
        $fields = array_column($data, 'value', 'name');

        self::assertSame('9', $fields['tx[item][__identity]']);
    }

    public function testHiddenIdentityFieldIsAddedForPersistedObject(): void
    {
        $viewHelper = $this->buildViewHelper([]);

        $object = $this->createMock(AbstractDomainObject::class);
        $object->method('_isNew')->willReturn(false);
        $object->method('getUid')->willReturn(42);

        $this->invokeRenderHiddenIdentityField($viewHelper, $object, 'login');

        self::assertSame(
            [['name' => 'login[__identity]', 'type' => 'hidden', 'value' => 42]],
            $this->getCollectedData($viewHelper)
        );
    }

    public function testHiddenIdentityFieldResolvesLazyLoadingProxy(): void
    {
        $viewHelper = $this->buildViewHelper([]);

        $object = $this->createMock(AbstractDomainObject::class);
        $object->method('_isNew')->willReturn(false);
        $object->method('getUid')->willReturn(7);

        $proxy = $this->createMock(LazyLoadingProxy::class);
        $proxy->method('_loadRealInstance')->willReturn($object);

        $this->invokeRenderHiddenIdentityField($viewHelper, $proxy, 'login');

        self::assertSame(
            [['name' => 'login[__identity]', 'type' => 'hidden', 'value' => 7]],
            $this->getCollectedData($viewHelper)
        );
    }

    public function testHiddenIdentityFieldIsSkippedForNewOrUidLessObjects(): void
    {
        $viewHelper = $this->buildViewHelper([]);

        $newObject = $this->createMock(AbstractDomainObject::class);
        $newObject->method('_isNew')->willReturn(true);
        $newObject->method('_isClone')->willReturn(false);

        $uidLessObject = $this->createMock(AbstractDomainObject::class);
        $uidLessObject->method('_isNew')->willReturn(false);
        $uidLessObject->method('getUid')->willReturn(null);

        $this->invokeRenderHiddenIdentityField($viewHelper, $newObject, 'login');
        $this->invokeRenderHiddenIdentityField($viewHelper, $uidLessObject, 'login');
        $this->invokeRenderHiddenIdentityField($viewHelper, 'not-an-object', 'login');

        self::assertSame([], $this->getCollectedData($viewHelper));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function buildViewHelper(array $arguments): LoginFormViewHelper
    {
        $viewHelper = new LoginFormViewHelper();

        $request = $this->createMock(RequestInterface::class);
        $request->method('getControllerExtensionName')->willReturn('Felogin');
        $request->method('getPluginName')->willReturn('Login');
        $request->method('getControllerName')->willReturn('Login');
        $request->method('getControllerActionName')->willReturn('login');
        $request->method('getArguments')->willReturn([]);
        $request->method('getAttribute')->willReturn(null);

        $this->viewHelperVariableContainer = new ViewHelperVariableContainer();

        $renderingContext = $this->createMock(RenderingContext::class);
        $renderingContext->method('getViewHelperVariableContainer')->willReturn($this->viewHelperVariableContainer);
        $renderingContext->method('hasAttribute')->willReturn(true);
        $renderingContext->method('getAttribute')->willReturn($request);

        $viewHelper->setRenderingContext($renderingContext);
        $viewHelper->setArguments($arguments);
        $viewHelper->injectHashService(new HashService());

        $extensionService = $this->createMock(ExtensionService::class);
        $extensionService->method('getPluginNamespace')->with('Felogin', 'Login')->willReturn('tx_felogin_login');
        $viewHelper->injectExtensionService($extensionService);

        $mvcPropertyMappingConfigurationService = $this->createMock(MvcPropertyMappingConfigurationService::class);
        $mvcPropertyMappingConfigurationService->method('generateTrustedPropertiesToken')->willReturn('trusted-token');
        $viewHelper->injectMvcPropertyMappingConfigurationService($mvcPropertyMappingConfigurationService);

        return $viewHelper;
    }

    private function invokeRenderHiddenIdentityField(LoginFormViewHelper $viewHelper, mixed $object, string $name): void
    {
        (new ReflectionMethod($viewHelper, 'renderHiddenIdentityField'))->invoke($viewHelper, $object, $name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCollectedData(LoginFormViewHelper $viewHelper): array
    {
        return (new ReflectionProperty($viewHelper, 'data'))->getValue($viewHelper);
    }
}
