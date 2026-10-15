.. _integrations-redirects:

==============
EXT:redirects
==============

In a headless setup the frontend application performs redirects. Whenever
headless mode applies to a request (`headless: 1`, or `headless: 2` with
exactly `Accept: application/json` as the first Accept header value),
EXT:headless turns the `30x` response TYPO3 would send into a JSON
envelope — no feature flag required.

Two kinds of redirects are covered, and they are wired differently:

* **Shortcut pages, mount points and site-base redirects** (e.g. `/` to
  `/en/`) come from core frontend middlewares. EXT:headless replaces them
  unconditionally, so they are JSON even when `EXT:redirects` is not
  installed:

  * `typo3/cms-frontend/base-redirect-resolver` →
    :php:`FriendsOfTYPO3\Headless\Middleware\SiteBaseRedirectResolver`
  * `typo3/cms-frontend/shortcut-and-mountpoint-redirect` → disabled and
    replaced by `headless/cms-frontend/shortcut-and-mountpoint-redirect`
    (:php:`FriendsOfTYPO3\Headless\Middleware\ShortcutAndMountPointRedirect`)

* **Redirect-manager records** need `EXT:redirects`
  (`composer require typo3/cms-redirects`). The core `redirecthandler`
  middleware stays in place; the JSON envelope is produced by the
  `headless/RedirectWasHit` event listener (see below), which is
  registered only when `EXT:redirects` is installed.

Outside headless mode — a `headless: 0` site, or a mixed-mode request
without the exact `Accept` header — the replaced middlewares return the
core `30x` response unchanged.

Requests for the headless page-content type (`?type=834`) bypass
shortcut and mount-point redirect handling entirely and are resolved
downstream instead.

JSON response shape
===================

A matched redirect produces:

.. code-block:: json

   {
     "redirectUrl": "https://example.com/new-target",
     "statusCode": 301
   }

`redirectUrl` is run through `UrlUtility::prepareRelativeUrlIfPossible()`,
so internal targets come back as relative paths (`/new-target`) when
they land on the same frontend host.

The HTTP response itself is always `200` — `statusCode` tells the
frontend which redirect to perform, and the frontend must apply the
usual method semantics itself (`301`/`302` may switch to GET,
`307`/`308` preserve the request method). Its value depends on the
source:

* redirect-manager records: the record's configured target status
  code;
* shortcut/mount-point and site-base redirects: the HTTP code the core
  middleware would have sent (typically `307`).

Customising the redirect
========================

The JSON envelope is built by
:php:`FriendsOfTYPO3\Headless\Event\Listener\HeadlessRedirectResponseListener`,
which listens to the core :php:`TYPO3\CMS\Redirects\Event\RedirectWasHitEvent`
(identifier `headless/RedirectWasHit`). Register your own listener for
the same event `after` it and replace the response:

.. code-block:: php

   final readonly class TagRedirectsForAnalytics
   {
       public function __invoke(RedirectWasHitEvent $event): void
       {
           $response = $event->getResponse();
           if (!$response instanceof JsonResponse) {
               return;
           }

           $payload = json_decode((string)$response->getBody(), true);
           $payload['redirectUrl'] .= '?utm_source=redirect';
           $event->setResponse(new JsonResponse($payload));
       }
   }

.. code-block:: yaml

   # Configuration/Services.yaml
   services:
     Vendor\MyExt\EventListener\TagRedirectsForAnalytics:
       tags:
         - name: event.listener
           identifier: 'myext/redirect/analytics'
           after: 'headless/RedirectWasHit'

Page targets carrying Extbase plugin parameters in the typolink
`additionalParams` segment are rebuilt through the site router by
:php:`FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver` (the core
`RedirectService` drops that segment unless "keep query parameters"
is enabled). Override or extend that service via `Services.yaml`
when you need different target URL resolution.

Short URLs & QR codes (5.x)
===========================

The "Short URLs" and "QR Codes" backend modules of `EXT:redirects` show
source URLs on the *TYPO3* host, which is wrong when the public site lives
on the frontend domain. With headless, both modules (and the QR-code/short-URL
fields in redirect records) resolve source URLs against the site's
`frontendBase` instead, so copied links and scanned codes land on the
public frontend. No configuration needed beyond `frontendBase`; resolution
logic can be replaced by overriding
:php:`FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver` via `Services.yaml`.
