# Upgrade from 4.x to 5.x

## Requirements

* TYPO3 v14, PHP >= 8.2. Staying on TYPO3 v12.4/v13? Use the `4.x` branch
  (bug & security fixes).
* Composer installation is recommended. Classic mode still works, but
  `ext_emconf.php` was removed — install by unpacking the release into
  `typo3conf/ext/headless`.

## Pick a response variant

5.x ships three variants. Each is available both as a site set and as a
sys_template static include:

| Site set | Static include | Output |
| --- | --- | --- |
| `friendsoftypo3/headless` | Headless | Trimmed default response (new projects) |
| `friendsoftypo3/headless-legacy` | Headless Legacy (4.x) | Full 4.x-compatible response (recommended for upgrades) |
| `friendsoftypo3/headless-mixed` | Headless - Mixed mode JSON response | Full 4.x-compatible response, served as JSON only for requests with exactly `Accept: application/json` |

The trimmed response drops `meta` (use `seo`) and the page/content element
`categories` fields — details in the TypoScript section below.

## Upgrading a site configured via sets (TYPO3 v13)

`friendsoftypo3/headless` shipped the *full* response in 4.x but ships the
*trimmed* one in 5.x. To keep your output unchanged, switch the dependency in
`config/sites/<identifier>/config.yaml` to the legacy set:

```yaml
dependencies:
  - friendsoftypo3/headless-legacy
```

Make sure the site has no root sys_template record: a record with "Clear"
flags wipes all set-provided TypoScript (symptom:
`No page configured for type=0`).

## Upgrading a site configured via sys_template

Existing records keep working without changes — but note what the include
selector items map to in 5.x:

* Records that include the 4.x "Headless" static keep the stored path
  (`EXT:headless/Configuration/TypoScript`) and keep producing the full 4.x
  response. That item is labelled **Headless Legacy (4.x)** in 5.x.
* The **Headless** item now points at the new trimmed response
  (`EXT:headless/Configuration/TypoScript/Headless`) — select it only once
  your frontend handles the 5.x output.
* The mixed-mode static kept its 4.x path
  (`EXT:headless/Configuration/TypoScript/Mixed`) — records including it are
  unaffected.
* Site packages that `@import`
  `EXT:headless/Configuration/TypoScript/setup.typoscript` directly also keep
  working and get the full 4.x response.

## TypoScript

* The trimmed response ships no `meta` (use `seo`) and no `categories` page
  fields; `lib.contentElement` defines no `categories` field.
* Files moved — adjust direct `@import`s: `Page/Meta.typoscript`,
  `Page/Categories.typoscript` and `Configuration/PageConfiguration.typoscript`
  now live under `TypoScript/Legacy/`.
* Removed: `lib.baseImage` (orphaned since 4.x; gallery content elements now
  share `lib.galleryContentElement`).
* Removed constants (define locally if referenced): `styles.content.allowTags`,
  `styles.content.shortcut.tables`, `styles.content.links.*`,
  `styles.content.textmedia.rowSpacing|textMargin|borderColor`.
* Legacy response: the content element `categories` key moved after
  `appearance` (key order only, same values).

## Feature flags

* Removed: `headless.redirectMiddlewares` — the redirects integration is now
  auto-enabled when EXT:redirects is installed.
* Reworked: `headless.overrideFluidTemplates` — swaps the core
  `ViewFactoryInterface` for `HeadlessViewFactory`; templates may now also be
  raw-PHP (`HeadlessPhpView`).

## API

Removed or replaced:

| 4.x | 5.x replacement |
| --- | --- |
| `Event\RedirectUrlEvent`, `Event\Listener\RedirectUrlAdditionalParamsListener`, `Middleware\RedirectHandler` | `Event\Listener\HeadlessRedirectResponseListener` on the core `RedirectWasHitEvent` (identifier `headless/RedirectWasHit`); register your own listener `after` it to customise the JSON redirect payload. Extbase `additionalParams` targets are resolved by `Redirects\TargetUrlResolver` (overridable via `Services.yaml`). |
| `XClass\Controller\PreviewController`, `XClass\Preview\PreviewUriBuilder`, `Hooks\PreviewUrlHook` | `Event\Listener\AfterPageUriGeneratedListener` |
| `XClass\ImageService` | `Resource\Service\HeadlessImageService` (aliased over the core service) |
| `XClass\ResourceLocalDriver` | `Event\Listener\ProxyResourcePublicUrlListener` |
| `XClass\TemplateView` | `View\HeadlessViewFactory` |
| `Hooks\FileOrFolderLinkBuilder` | `Typolink\FileOrFolderLinkBuilder` (moved) |

Other changes:

* Type against `Utility\FileUtilityInterface` instead of the concrete
  `FileUtility`.

## New in 5.x

* Installed core extensions are detected and integrated automatically —
  `EXT:form`, `EXT:felogin`, `EXT:redirects`, `EXT:seo` — honouring the
  site's headless/mixed mode.
* Redirect "Short URLs" / "QR Codes" backend modules resolve source URLs
  against the site's `frontendBase`.
* `Form\Decorator\RichTextFormDefinitionDecorator` for TYPO3 v14.2
  RTE-enabled form fields.
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
