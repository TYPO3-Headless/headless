.. _integrations-felogin:

============
EXT:felogin
============

`EXT:felogin` works out of the box with headless. The headless XClass
on `LoginController` swaps the HTML view for a JSON response that
includes the form definition, the login status and any redirect
target.

Setup
=====

Standard felogin setup — install (`composer require typo3/cms-felogin`),
drop a login plugin onto a page, configure storage `pages` in plugin
settings.

You can test the flow without a frontend with `curl`:

.. code-block:: bash

   curl -i -X POST https://api.example.com/login-page \
     -d 'user=joe&pass=secret&logintype=login'

A successful login responds with a `set-cookie` header carrying the
session cookie (`fe_typo_user`). On failure the JSON `status` flips
to `failure` and `message` carries the rendered header/text for the
failure state.

JSON response shape
===================

The plugin output sits under the content element's `content.data` key:

.. code-block:: json

   {
     "content": {
       "colPos0": [{
         "type": "felogin_login",
         "content": {
           "data": {
             "form": {
               "title": "Login",
               "action": "https://api.example.com/login-page",
               "method": "post",
               "elements": []
             },
             "message": { "header": "…", "message": "…" },
             "status": "success",
             "recovery": null,
             "flashMessages": []
           }
         }
       }]
     }
   }

`form.elements` contains every input the frontend must submit,
including the hidden fields (`logintype`, `pid`, redirect fields and
the `__RequestToken` — a TYPO3 RequestToken with scope
`core/user-auth/fe`). Post them back verbatim for the login to be
accepted.

When a redirect target applies, the plugin payload is replaced
entirely by `{ "redirectUrl": "…", "statusCode": 303, "status": "…" }`
— the frontend performs the redirect itself.

Cookies & cross-domain
======================

The session cookie is scoped to the API domain by default. If your
frontend lives on a different host, set a shared
`$GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain']`, or enable the
`headless.cookieDomainPerSite` feature flag to derive it per site —
see :ref:`multisite`.

Detect login in your own code
=============================

.. code-block:: php

   use TYPO3\CMS\Core\Context\Context;

   $context = GeneralUtility::makeInstance(Context::class);
   $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');

Or for the user's data:

.. code-block:: php

   $userId = (int)$context->getPropertyFromAspect('frontend.user', 'id');
   $groups = $context->getPropertyFromAspect('frontend.user', 'groupIds');

Listening for successful login
==============================

Headless ships `LoginConfirmedEventListener` that decorates the JSON
view with `status = success`. To run your own logic at the same
point, listen to TYPO3 core's
`TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent` — see
:ref:`developer-events` for listener registration.
