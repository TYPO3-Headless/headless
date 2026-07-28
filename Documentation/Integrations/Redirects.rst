.. _integrations-redirects:

==============
EXT:redirects
==============

Install `EXT:redirects` (`composer require typo3/cms-redirects`) and
the standard redirect manager works as usual. EXT:headless then
automatically swaps the relevant frontend middlewares so a matched
redirect surfaces to a headless frontend as JSON rather than a `30x`
response — no feature flag required.

The replaced middlewares are:

* `typo3/cms-frontend/base-redirect-resolver` →
  `FriendsOfTYPO3\Headless\Middleware\SiteBaseRedirectResolver`
* `typo3/cms-frontend/shortcut-and-mountpoint-redirect` →
  `FriendsOfTYPO3\Headless\Middleware\ShortcutAndMountPointRedirect`

Matched redirects from the redirect manager are turned into JSON by the
`headless/RedirectWasHit` event listener (see below) — the core
`redirecthandler` middleware stays in place.

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

Customising the redirect
========================

The JSON envelope is built by
`FriendsOfTYPO3\Headless\Event\Listener\HeadlessRedirectResponseListener`,
which listens to the core `TYPO3\CMS\Redirects\Event\RedirectWasHitEvent`
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
`FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver` (the core
`RedirectService` drops that segment unless "keep query parameters"
is enabled). Override or extend that service via `Services.yaml`
when you need different target URL resolution.

Short URLs & QR codes (5.x)
===========================

The "Short URLs" and "QR Codes" backend modules of `EXT:redirects` show
source URLs on the *TYPO3* host — useless when the public site lives on the
frontend domain. With headless, both modules (and the QR-code/short-URL
fields in redirect records) resolve source URLs against the site's
`frontendBase` instead, so copied links and scanned codes land on the
public frontend. No configuration needed beyond `frontendBase`; resolution
logic can be replaced by overriding
`FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver` via `Services.yaml`.
