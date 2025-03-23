<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\XClass\ViewHelpers;

use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Security\HashScope;

class FormViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper
{
    protected array $data = [];

    public function render(): string
    {
        foreach ($this->tag->getAttributes() as $key => $value) {
            if (str_starts_with($key, 'data-')) {
                $key = substr($key, strpos('data-', $key) + 5);
                $this->data['data'][$key] = $value;
                continue;
            }

            $this->data[$key] = $value;
        }

        if (!$this->renderingContext->hasAttribute(ServerRequestInterface::class)
            || !$this->renderingContext->getAttribute(ServerRequestInterface::class) instanceof RequestInterface) {
            throw new RuntimeException(
                'ViewHelper f:form can be used only in extbase context and needs a request implementing extbase RequestInterface.',
                1639821904
            );
        }

        $this->setFormActionUri();

        if ($this->tag->getAttribute('action') !== null) {
            $this->data['action'] = $this->tag->getAttribute('action');
        }

        // Force 'method="get"' or 'method="post"', defaulting to "post".
        if (isset($this->arguments['method']) && strtolower($this->arguments['method']) === 'get') {
            $this->data['method'] = 'get';
        } else {
            $this->data['method'] = 'post';
        }

        if (!empty($this->arguments['name'])) {
            $this->data['name'] = $this->arguments['name'];
        }

        if (isset($this->arguments['novalidate']) && $this->arguments['novalidate'] === true) {
            $this->data['novalidate'] = 'novalidate';
        }

        $this->addFormObjectNameToViewHelperVariableContainer();
        $this->addFormObjectToViewHelperVariableContainer();
        $this->addFieldNamePrefixToViewHelperVariableContainer();
        $this->addFormFieldNamesToViewHelperVariableContainer();

        $children = trim($this->renderChildren());
        $children = preg_replace('!}\s*{!', '},{', $children);
        $children = preg_replace("!\r?\n!", '', $children);
        $children = '{"elements": [' . $children . ']}';
        $children = json_decode($children, true);

        if ($children !== null && isset($children['elements'])) {
            $this->data['elements'] = $children['elements'];
        }

        if (isset($this->arguments['hiddenFieldClassName']) && $this->arguments['hiddenFieldClassName'] !== null) {
            $this->data['hiddenFieldClassName'] = htmlspecialchars($this->arguments['hiddenFieldClassName']);
        }

        $this->renderHiddenIdentityField($this->arguments['object'] ?? null, $this->getFormObjectName());
        $this->renderAdditionalIdentityFields();
        $this->renderHiddenReferrerFields();
        $this->renderRequestTokenHiddenField();

        // Render the trusted list of all properties after everything else has been rendered
        $this->renderTrustedPropertiesField();

        //$content .= $formContent;
        $this->removeFieldNamePrefixFromViewHelperVariableContainer();
        $this->removeFormObjectFromViewHelperVariableContainer();
        $this->removeFormObjectNameFromViewHelperVariableContainer();
        $this->removeFormFieldNamesFromViewHelperVariableContainer();
        $this->removeCheckboxFieldNamesFromViewHelperVariableContainer();

        return json_encode($this->data);
    }

    /**
     * Render the request hash field
     */
    protected function renderTrustedPropertiesField(): string
    {
        $formFieldNames = $this->renderingContext->getViewHelperVariableContainer()->get(\TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper::class, 'formFieldNames');
        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken($formFieldNames, $this->getFieldNamePrefix());

        $this->addHiddenField('__trustedProperties', $requestHash);

        return '';
    }

    /**
     * Renders a hidden form field containing the technical identity of the given object.
     *
     * @param mixed $object Object to create the identity field for. Non-objects are ignored.
     * @param string|null $name Name
     * @return string A hidden field containing the Identity (uid) of the given object
     * @see \TYPO3\CMS\Extbase\Mvc\Controller\Argument::setValue()
     */
    protected function renderHiddenIdentityField(mixed $object, ?string $name): string
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
        }
        if (!is_object($object)
            || !($object instanceof AbstractDomainObject)
            || ($object->_isNew() && !$object->_isClone())) {
            return '';
        }
        // Intentionally NOT using PersistenceManager::getIdentifierByObject here.
        // Using that one breaks re-submission of data in forms in case of an error.
        $identifier = $object->getUid();
        if ($identifier === null) {
            return '';
        }
        $name = $this->prefixFieldName($name ?? '') . '[__identity]';
        $this->registerFieldNameForFormTokenGeneration($name);

        $this->addHiddenField($name, $identifier);

        return '';
    }

    protected function renderRequestTokenHiddenField(): string
    {
        $requestToken = $this->arguments['requestToken'] ?? null;
        $signingType = $this->arguments['signingType'] ?? null;

        $isTrulyRequestToken = is_int($requestToken) && $requestToken === 1
            || is_string($requestToken) && strtolower($requestToken) === 'true';
        $formAction = $this->tag->getAttribute('action');

        // basically "request token, yes" - uses form-action URI as scope
        if ($isTrulyRequestToken || $requestToken === '@nonce') {
            $requestToken = RequestToken::create($formAction);
            // basically "request token with 'my-scope'" - uses 'my-scope'
        } elseif (is_string($requestToken) && $requestToken !== '') {
            $requestToken = RequestToken::create($requestToken);
        }
        if (!$requestToken instanceof RequestToken) {
            return '';
        }
        if (strtolower((string)($this->arguments['method'] ?? '')) === 'get') {
            throw new LogicException('Cannot apply request token for forms sent via HTTP GET', 1651775963);
        }

        $context = GeneralUtility::makeInstance(Context::class);
        $securityAspect = SecurityAspect::provideIn($context);
        // @todo currently defaults to 'nonce', there might be a better strategy in the future
        $signingType = $signingType ?: 'nonce';
        $signingProvider = $securityAspect->getSigningSecretResolver()->findByType($signingType);
        if ($signingProvider === null) {
            throw new LogicException(sprintf('Cannot find request token signing type "%s"', $signingType), 1664260307);
        }

        $signingSecret = $signingProvider->provideSigningSecret();
        $requestToken = $requestToken->withMergedParams(['request' => ['uri' => $formAction]]);

        $this->addHiddenField(RequestToken::PARAM_NAME, $requestToken->toHashSignedJwt($signingSecret));

        return '';
    }

    /**
     * Render additional identity fields which were registered by form elements.
     * This happens if a form field is defined like property="bla.blubb" - then we might need an identity property for the sub-object "bla".
     *
     * @return string HTML-string for the additional identity properties
     */
    protected function renderAdditionalIdentityFields(): string
    {
        if ($this->viewHelperVariableContainer->exists(\TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper::class, 'additionalIdentityProperties')) {
            $additionalIdentityProperties = $this->viewHelperVariableContainer->get(FormViewHelper::class, 'additionalIdentityProperties');
            foreach ($additionalIdentityProperties as $identity) {
                $this->addHiddenField('identity', $identity);
            }
        }

        return '';
    }

    protected function renderHiddenReferrerFields(): string
    {
        /** @var RequestInterface $request */
        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        $extensionName = $request->getControllerExtensionName();
        $controllerName = $request->getControllerName();
        $actionName = $request->getControllerActionName();
        $actionRequest = [
            '@extension' => $extensionName,
            '@controller' => $controllerName,
            '@action' => $actionName,
        ];

        $this->addHiddenField('__referrer[@extension]', htmlspecialchars($extensionName));
        $this->addHiddenField('__referrer[@controller]', htmlspecialchars($controllerName));
        $this->addHiddenField('__referrer[@action]', htmlspecialchars($actionName));
        $this->addHiddenField('__referrer[arguments]', htmlspecialchars($this->hashService->appendHmac(base64_encode(serialize($request->getArguments())), HashScope::ReferringArguments->prefix())));
        $this->addHiddenField('__referrer[@request]', htmlspecialchars($this->hashService->appendHmac(json_encode($actionRequest), HashScope::ReferringRequest->prefix())));

        return '';
    }

    protected function addHiddenField(string $name, mixed $value): void
    {
        $tmp = [];
        $tmp['name'] = $name;
        $tmp['type'] = 'hidden';
        $tmp['value'] = $value;
        $this->data['elements'][] = $tmp;
    }
}
