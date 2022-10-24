<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ViewHelpers;

use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;

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
     * @var array
     */
    protected $data = [];

    protected $i = 0;

    /**
     * Render the form.
     *
     * @return string rendered form
     */
    public function render()
    {
        $this->setFormActionUri();
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
     * Sets the "action" attribute of the form tag
     */
    protected function setFormActionUri()
    {
        if ($this->hasArgument('actionUri')) {
            $formActionUri = $this->arguments['actionUri'];
        } else {
            if (isset($this->arguments['noCacheHash'])) {
                trigger_error(
                    'Using the argument "noCacheHash" in <f:form> ViewHelper has no effect anymore. Remove the argument in your fluid template, as it will result in a fatal error.',
                    E_USER_DEPRECATED
                );
            }
            /** @var UriBuilder $uriBuilder */
            $uriBuilder = $this->renderingContext->getControllerContext()->getUriBuilder();
            $uriBuilder
                ->reset()
                ->setTargetPageType($this->arguments['pageType'] ?? 0)
                ->setNoCache($this->arguments['noCache'] ?? false)
                ->setSection($this->arguments['section'] ?? '')
                ->setCreateAbsoluteUri($this->arguments['absolute'] ?? false)
                ->setArguments(isset($this->arguments['additionalParams']) ? (array)$this->arguments['additionalParams'] : [])
                ->setAddQueryString($this->arguments['addQueryString'] ?? false)
                ->setArgumentsToBeExcludedFromQueryString(isset($this->arguments['argumentsToBeExcludedFromQueryString']) ? (array)$this->arguments['argumentsToBeExcludedFromQueryString'] : [])
                ->setFormat($this->arguments['format'] ?? '');

            $pageUid = (int)($this->arguments['pageUid'] ?? 0);
            if ($pageUid > 0) {
                $uriBuilder->setTargetPageUid($pageUid);
            }

            $formActionUri = $uriBuilder->uriFor(
                $this->arguments['action'] ?? null,
                $this->arguments['arguments'] ?? [],
                $this->arguments['controller'] ?? null,
                $this->arguments['extensionName'] ?? null,
                $this->arguments['pluginName'] ?? null
            );
            $this->formActionUriArguments = $uriBuilder->getArguments();
        }
    }

    /**
     * Render additional identity fields which were registered by form elements.
     * This happens if a form field is defined like property="bla.blubb" - then we might need an identity property for the sub-object "bla".
     *
     * @return string HTML-string for the additional identity properties
     */
    protected function renderAdditionalIdentityFields()
    {
        if ($this->viewHelperVariableContainer->exists(FormViewHelper::class, 'additionalIdentityProperties')) {
            $additionalIdentityProperties = $this->viewHelperVariableContainer->get(FormViewHelper::class, 'additionalIdentityProperties');
            $output = '';
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
    protected function renderHiddenReferrerFields()
    {
        $request = $this->renderingContext->getControllerContext()
            ->getRequest();
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
            '__referrer[@request]',
            $this->hashService->appendHmac(json_encode($actionRequest))
        );
    }

    /**
     * Adds the field name prefix to the ViewHelperVariableContainer
     */
    protected function addFieldNamePrefixToViewHelperVariableContainer()
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
    protected function renderHiddenIdentityField($object, $name)
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
        // Intentionally NOT using PersistenceManager::getIdentifierByObject here!!
        // Using that one breaks re-submission of data in forms in case of an error.
        $identifier = $object->getUid();
        if ($identifier === null) {
            return '';
        }
        $name = $this->prefixFieldName($name) . '[__identity]';
        $this->registerFieldNameForFormTokenGeneration($name);

        $this->addHiddenField($name, $identifier);
    }

    /**
     * Render the request hash field
     */
    protected function renderTrustedPropertiesField()
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
    }

    protected function addHiddenField($name, $value)
    {
        $tmp = [];
        $tmp['name'] = $name;
        $tmp['type'] = 'hidden';
        $tmp['value'] = $value;
        $this->data[] = $tmp;
    }
}
