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

You can test the flow without a frontend with `curl`. TYPO3 v14 only
evaluates credentials that are accompanied by a valid, nonce-signed
`__RequestToken`, so a login is always two requests: fetch the form
first, then post it back together with its cookies.

.. code-block:: bash

   # 1) Fetch the login form; store the typo3nonce_* cookie
   curl -s -c cookies.txt -H 'Accept: application/json' \
     https://api.example.com/login-page

   # 2) Post credentials plus EVERY hidden field returned in
   #    form.elements, sending the cookies back — the __RequestToken
   #    is only valid together with its typo3nonce_* cookie
   curl -i -b cookies.txt -c cookies.txt -X POST \
     -H 'Accept: application/json' \
     https://api.example.com/login-page \
     --data-urlencode 'user=joe' \
     --data-urlencode 'pass=secret' \
     --data-urlencode 'logintype=login' \
     --data-urlencode 'pid=<value from form.elements>' \
     --data-urlencode '__RequestToken=<value from form.elements>'
     # …plus all remaining hidden fields from form.elements, verbatim

A successful login responds with a `set-cookie` header carrying the
session cookie (`fe_typo_user`). On failure the JSON `status` flips
to `failure` and `message` carries the rendered header/text for the
failure state.

.. note::

   On a MIXED-mode site (`headless: 2`) every request shown here must
   send exactly `Accept: application/json` as the first Accept header
   value. Lists such as `application/json, text/plain, */*` (the
   axios/fetch default) or a `;charset=` suffix fall back to HTML
   rendering.

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
               "method": "POST",
               "elements": []
             },
             "message": { "header": "…", "message": "…" },
             "status": "success",
             "recovery": "https://api.example.com/login-page?…",
             "flashMessages": []
           }
         }
       }]
     }
   }

`recovery` always carries the password-recovery URI (never `null`).

`form.elements` contains every input the frontend must submit. Echo
each hidden field back verbatim, under its exact `name`:

* `logintype`, `pid`, `redirect_url`, `noredirect`,
  `redirectReferrer`, `referer` and — when permalogin is offered —
  `permalogin`: plain felogin fields, unprefixed.
* `tx_felogin_login[__referrer][@extension]`, `…[@controller]`,
  `…[@action]`, `…[arguments]`, `…[@request]`: extbase referrer
  fields, HMAC-protected.
* `tx_felogin_login[__trustedProperties]`: the extbase property
  mapping token. It is emitted *with* the plugin prefix and must be
  posted back under that prefixed name.
* `__RequestToken`: a TYPO3 RequestToken with scope
  `core/user-auth/fe`, signed against the `typo3nonce_*` cookie set
  on the GET request. This is the one field that stays
  **unprefixed**.

Without a complete, unmodified set of these fields the login is
rejected before credentials are even checked.

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
