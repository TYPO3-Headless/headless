Upgrade from 4.x to 5.x
=======================

Requirements
--

* TYPO3 v14, PHP >= 8.2. Staying on TYPO3 v12.4/v13? Use the `4.x` branch (bug & security fixes).
* Composer install is recommended. Classic mode still works, but `ext_emconf.php` was
  removed — install manually by unpacking the release into `typo3conf/ext/headless`.

Site setup: static template → site sets
--

1. Add one set to your site config `dependencies`:
   `friendsoftypo3/headless` (new, trimmed response), `friendsoftypo3/headless-legacy`
   (full 4.x-compatible response — the upgrade default), or `friendsoftypo3/headless-mixed`.
   Already on sets (TYPO3 v13)? `friendsoftypo3/headless` shipped the *full* response in
   4.x but the *trimmed* one in 5.x — switch the dependency to
   `friendsoftypo3/headless-legacy` to keep your output unchanged.
2. Delete the root sys_template record. A record with "Clear" flags wipes all
   set-provided TypoScript (symptom: `No page configured for type=0`).
3. The old static includes are no longer selectable. Existing sys_template records
   referencing `EXT:headless/Configuration/TypoScript`, and site packages importing
   `.../TypoScript/setup.typoscript` directly, keep working — the file remains as a
   shim producing the full 4.x response.

TypoScript
--

* The default set ships a trimmed page response: no `meta` (use `seo`) and no `categories`
  page fields; `lib.contentElement` defines no `categories` field.
  Use `friendsoftypo3/headless-legacy` for byte-compatible 4.x output.
* Files moved — adjust direct `@import`s: `Page/Meta.typoscript`, `Page/Categories.typoscript`
  and `Configuration/PageConfiguration.typoscript` now live under `TypoScript/Legacy/`.
* Removed: `lib.baseImage` (orphaned since 4.x; gallery CEs now share
  `lib.galleryContentElement`) and the unused constants `styles.content.allowTags`,
  `styles.content.shortcut.tables`, `styles.content.links.*`,
  `styles.content.textmedia.rowSpacing|textMargin|borderColor` — define locally if referenced.
* Legacy response: the content element `categories` key moved after `appearance`
  (key order only, same values).

Feature flags
--

* Removed: `headless.redirectMiddlewares` — the redirects integration is now auto-enabled
  when EXT:redirects is installed. (Older flags `nextMajor`, `jsonViewModule`,
  `pageTitleProviders` were already gone before 4.8; `headless.workspaces` never existed
  in 4.x — workspace preview was auto-enabled there too.)
* Reworked: `headless.overrideFluidTemplates` — swaps the core `ViewFactoryInterface`
  for `HeadlessViewFactory`; templates may now also be raw-PHP (`HeadlessPhpView`).

API
--

* `Event\RedirectUrlEvent` + `Event\Listener\RedirectUrlAdditionalParamsListener` and
  `Middleware\RedirectHandler` were removed. The JSON redirect envelope is built by
  `Event\Listener\HeadlessRedirectResponseListener` on the core `RedirectWasHitEvent`
  (identifier `headless/RedirectWasHit`) — register your own listener `after` it to
  customise the payload. Extbase `additionalParams` targets are resolved by
  `Redirects\TargetUrlResolver` (overridable via `Services.yaml`).
* XClasses removed in favour of core APIs:
  `XClass\Controller\PreviewController`, `XClass\Preview\PreviewUriBuilder` and
  `Hooks\PreviewUrlHook` → `Event\Listener\AfterPageUriGeneratedListener`;
  `XClass\ImageService` → `Resource\Service\HeadlessImageService` (aliased over the core
  service); `XClass\ResourceLocalDriver` → `Event\Listener\ProxyResourcePublicUrlListener`;
  `XClass\TemplateView` → `View\HeadlessViewFactory`.
* `Hooks\FileOrFolderLinkBuilder` moved to `Typolink\FileOrFolderLinkBuilder`.
* Type against `Utility\FileUtilityInterface` instead of the concrete `FileUtility`.
* Service and middleware wiring moved from `ext_localconf.php` to
  `Configuration/Services.php` / `Configuration/RequestMiddlewares.php`;
  `ext_localconf.php` keeps only registrations without a DI seam (XCLASSes,
  typolink builder, FormEngine nodes, meta-tag managers, media renderers).

New in 5.x
--

* Installed core extensions are detected and integrated automatically — `EXT:form`,
  `EXT:felogin`, `EXT:redirects`, `EXT:seo` — honouring the site's headless/mixed mode.

* Redirect "Short URLs" / "QR Codes" backend modules resolve source URLs against the
  site's `frontendBase`.
* `Form\Decorator\RichTextFormDefinitionDecorator` for TYPO3 v14.2 RTE-enabled form fields.
* `JsonRedirect` form finisher is auto-registered in the form editor
  (set `friendsoftypo3/headless-form`).
* Workspace preview works through core rendering plus the generic backend
  preview-URL rewrite (`AfterPageUriGeneratedListener`) — the 4.x workspace
  XClasses are gone, no flag involved.
* `headless-language-menu` data processor (headless `LanguageMenuProcessor`).

Upgrade from 3.x to 4.x (BC release)
=======================

TYPO3 versions support
--
* `4.x` will support TYPO3 >= `12.4` **only**, if you are still on `11.5` please use `3.x` branch instead.

Removal of JSON output schema (2.x) typoscript template
--
With 4.x release we are removing old typoscript template (2.x). Please move your
instance to new output.

Upgrade from 2.x to 3.x (BC release)
=======================

TYPO3 versions support
--
* `3.x` will support TYPO3 >= `11.5` **only**, if you are still on `10.4`/`9.5` please use `2.x` branch instead.

Feature flags
--
* `FrontendBaseUrlInPagePreview` flag will be removed. Please use `headless.frontendUrls` instead (available since 2.5 release)
* `headless.simplifiedLinkTarget` flag will be removed. Setting will on by default

API
--

__Stuff to be removed:__

* `FriendsOfTYPO3\Headless\Utility\ContentUtility` will be removed. Please use new `CONTENT_JSON` content object
* `FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility` will be removed. Please use `FriendsOfTYPO3\Headless\Utility\UrlUtility`
* `FriendsOfTYPO3\Headless\Service\SiteService` will be removed. Please use `FriendsOfTYPO3\Headless\Utility\UrlUtility`
* `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['headless']['hooks']['redirectUrl']` hook will be removed. Please use `FriendsOfTYPO3\Headless\Event\RedirectUrlEvent`

__Changed behavior:__
* `FriendsOfTYPO3\Headless\Json\JsonEncoder` have dropped array input as requirement, so you can now encode objects etc, also by default encoder do not checks for possible json to decode, you have manually use `FriendsOfTYPO3\Headless\Json\JsonDecoder`
* `FriendsOfTYPO3\Headless\Hooks\TypolinkHook`. Main functionality is replaced with native TYPO3 core feature. TypolinkHook serving temporarily as fallback for 2.x page output only
Typoscript
--
* By default, json output is streamlined & optimized. If you do not want to rewrite frontend app, please use `Configuration/TypoScript/2.x/setup.typoscript` instead default one.
* If you would like to use version `2.x` of page output and do not touch frontend app, please also enable `headless.supportOldPageOutput` (restores default behavior with dataprocessing & typolink) flag in LocalConfiguration.php or AdditionalConfiguration.php
* domains listing for configure frontend endpoint in 3.x will be `835` by default instead of `1608564571`, also default SiteProvider will check if domain is marked as `headless`
