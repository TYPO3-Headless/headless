<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ViewHelpers;

use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;

use function base64_encode;

use function is_int;
use function is_object;
use function is_string;
use function json_encode;
use function serialize;
use function sprintf;
use function strtolower;

/**
 * Form ViewHelper. Generates a :html:`<form>` Tag.
 *
 * Basic usage
 * ===========
 *
 * Use :html:`<f:form>` to output an HTML :html:`<form>` tag which is targeted
 * at the specified action, in the current controller and package.
 * It will submit the form data via a POST request. If you want to change this,
 * use :html:`method="get"` as an argument.
 *
 * Examples
 * ========
 *
 * A complex form with a specified encoding type
 * ---------------------------------------------
 *
 * Form with enctype set::
 *
 *    <f:form action=".." controller="..." package="..." enctype="multipart/form-data">...</f:form>
 *
 * A Form which should render a domain object
 * ------------------------------------------
 *
 * Binding a domain object to a form::
 *
 *    <f:form action="..." name="customer" object="{customer}">
 *       <f:form.hidden property="id" />
 *       <f:form.textbox property="name" />
 *    </f:form>
 *
 * This automatically inserts the value of ``{customer.name}`` inside the
 * textbox and adjusts the name of the textbox accordingly.
 *
 * @codeCoverageIgnore
 */
class LoginFormViewHelper extends FormViewHelper
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $data = [];
    protected int $i = 0;

    /**
     * Render the form.
     *
     * @return string rendered form
     */
    public function render(): string
    {
        $renderingContext = $this->renderingContext;
        $request = $renderingContext->getRequest();
        if (!$request instanceof RequestInterface) {
            throw new \RuntimeException(
                'ViewHelper f:form can be used only in extbase context and needs a request implementing extbase RequestInterface.',
                1639821904
            );
        }

        $this->setFormActionUri();

        // Force 'method="get"' or 'method="post"', defaulting to "post".
        if (isset($this->arguments['method']) && strtolower($this->arguments['method']) === 'get') {
            $this->tag->addAttribute('method', 'get');
        } else {
            $this->tag->addAttribute('method', 'post');
        }

        if (isset($this->arguments['novalidate']) && $this->arguments['novalidate'] === true) {
            $this->tag->addAttribute('novalidate', 'novalidate');
        }

        $this->addFormObjectNameToViewHelperVariableContainer();
        $this->addFormObjectToViewHelperVariableContainer();
        $this->addFieldNamePrefixToViewHelperVariableContainer();
        $this->addFormFieldNamesToViewHelperVariableContainer();

        $this->data = $this->renderChildren();

        $this->renderHiddenIdentityField($this->arguments['object'] ?? null, $this->getFormObjectName());
        $this->renderAdditionalIdentityFields();
        $this->renderHiddenReferrerFields();
        $this->renderRequestTokenHiddenField();

        // Render the trusted list of all properties after everything else has been rendered
        $this->renderTrustedPropertiesField();

        $this->removeFieldNamePrefixFromViewHelperVariableContainer();
        $this->removeFormObjectFromViewHelperVariableContainer();
        $this->removeFormObjectNameFromViewHelperVariableContainer();
        $this->removeFormFieldNamesFromViewHelperVariableContainer();
        $this->removeCheckboxFieldNamesFromViewHelperVariableContainer();

        return json_encode($this->data);
    }

    /**
     * Render additional identity fields which were registered by form elements.
     * This happens if a form field is defined like property="bla.blubb" - then we might need an identity property for the sub-object "bla".
     *
     * @return string HTML-string for the additional identity properties
     */
    protected function renderAdditionalIdentityFields(): string
    {
        if ($this->viewHelperVariableContainer->exists(FormViewHelper::class, 'additionalIdentityProperties')) {
            $additionalIdentityProperties = $this->viewHelperVariableContainer->get(FormViewHelper::class, 'additionalIdentityProperties');
            foreach ($additionalIdentityProperties as $identity) {
                $this->addHiddenField('identity', $identity);
            }
        }

        return '';
    }

    /**
     * Renders hidden form fields for referrer information about
     * the current controller and action.
     *
     * @return string Hidden fields with referrer information
     * @todo filter out referrer information that is equal to the target (e.g. same packageKey)
     */
    protected function renderHiddenReferrerFields(): string
    {
        $renderingContext = $this->renderingContext;
        /** @var RequestInterface $request */
        $request = $renderingContext->getRequest();
        $extensionName = $request->getControllerExtensionName();
        $controllerName = $request->getControllerName();
        $actionName = $request->getControllerActionName();
        $actionRequest = [
            '@extension' => $extensionName,
            '@controller' => $controllerName,
            '@action' => $actionName,
        ];

        $this->addHiddenField(
            '__referrer[@extension]',
            $extensionName
        );
        $this->addHiddenField(
            '__referrer[@controller]',
            $controllerName
        );
        $this->addHiddenField(
            '__referrer[@action]',
            $actionName
        );
        $this->addHiddenField(
            '__referrer[arguments]',
            $this->hashService->appendHmac(base64_encode(serialize($request->getArguments())))
        );
        $this->addHiddenField(
            '__referrer[@request]',
            $this->hashService->appendHmac(json_encode($actionRequest))
        );

        return '';
    }

    /**
     * Adds the field name prefix to the ViewHelperVariableContainer
     */
    protected function addFieldNamePrefixToViewHelperVariableContainer(): void
    {
        $fieldNamePrefix = $this->getFieldNamePrefix();
        $this->viewHelperVariableContainer->add(FormViewHelper::class, 'fieldNamePrefix', $fieldNamePrefix);
    }

    /**
     * Renders a hidden form field containing the technical identity of the given object.
     *
     * @param object $object Object to create the identity field for
     * @param string $name Name
     *
     * @return string A hidden field containing the Identity (UID in TYPO3 Flow, uid in Extbase) of the given object or NULL if the object is unknown to the persistence framework
     * @see \TYPO3\CMS\Extbase\Mvc\Controller\Argument::setValue()
     */
    protected function renderHiddenIdentityField(?object $object, ?string $name): string
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
        }
        if (!is_object($object)
            || !($object instanceof AbstractDomainObject)
            || ($object->_isNew() && !$object->_isClone())
        ) {
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
    }

    /**
     * Render the request hash field
     */
    protected function renderTrustedPropertiesField(): string
    {
        $formFieldNames
            = $this->viewHelperVariableContainer->get(
                FormViewHelper::class,
                'formFieldNames'
            );
        $requestHash
            = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken(
                $formFieldNames,
                $this->getFieldNamePrefix()
            );
        $this->addHiddenField('__trustedProperties', $requestHash);

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
            throw new \LogicException('Cannot apply request token for forms sent via HTTP GET', 1651775963);
        }

        $context = GeneralUtility::makeInstance(Context::class);
        $securityAspect = SecurityAspect::provideIn($context);
        // @todo currently defaults to 'nonce', there might be a better strategy in the future
        $signingType = $signingType ?: 'nonce';
        $signingProvider = $securityAspect->getSigningSecretResolver()->findByType($signingType);
        if ($signingProvider === null) {
            throw new \LogicException(sprintf('Cannot find request token signing type "%s"', $signingType), 1664260307);
        }

        $signingSecret = $signingProvider->provideSigningSecret();
        $requestToken = $requestToken->withMergedParams(['request' => ['uri' => $formAction]]);

        $this->addHiddenField(RequestToken::PARAM_NAME, $requestToken->toHashSignedJwt($signingSecret));

        return '';
    }

    protected function addHiddenField(string $name, mixed $value): void
    {
        $tmp = [];
        $tmp['name'] = $name;
        $tmp['type'] = 'hidden';
        $tmp['value'] = $value;
        $this->data[] = $tmp;
    }
}
