.. _frontend:

=================
Consuming the API
=================

A frontend renders the response by walking the JSON: page, backend layout,
columns, content elements by `type`. The examples on this page use `nuxt-typo3 <https://github.com/TYPO3-Headless/nuxt-typo3>`__
(`@t3headless/nuxt-typo3`, Nuxt 3/4), which maps the response to Vue
components by convention. Request mechanics (headers, cookies, CORS) are in
:ref:`requests`.

Fetching a page
===============

A page is requested at its own URL on the API host, language prefix included.
The keys a frontend uses:

* `id`, `type`, `slug` — record uid, page type (`Standard`, `Shortcut`, …), path.
* `appearance.backendLayout` — which columns exist; `appearance.layout` — the
  page layout selected in the page properties.
* `content` — content elements grouped by column: `colPos0`, `colPos1`, …
* `seo` — title, meta tags, hreflang links, `<html>`/`<body>` attributes
  (:ref:`ref-typoscript`).
* `breadcrumbs`, `i18n`, `media` — rootline, language list, page media.

nuxt-typo3 handles this in a catch-all route. `useT3Page()` fetches the page for
the current route from `typo3.api.baseUrl`, keeps it in `pageData` and derives
the layout names:

.. code-block:: vue

   <!-- pages/[...slug].vue -->
   <template>
     <NuxtLayout :name="frontendLayout">
       <T3BackendLayout
         v-if="pageData?.content"
         :name="backendLayout"
         :content="pageData.content"
       />
     </NuxtLayout>
   </template>

   <script setup lang="ts">
   const { headData, pageData, backendLayout, frontendLayout } = await useT3Page()
   useHead(headData)
   definePageMeta({
      layout: false
      // for nuxt4 also add t3Page: true
   }
   )
   </script>

Call `useT3Page()` once per page; elsewhere read `pageData` from `useT3Api()`
or use `useT3Page({ fetchOnInit: false })`. `useT3Api().getPage(path)` fetches
any other page on demand.

.. _frontend-initial-data:

Initial data (`?type=834`)
==========================

Site-wide data that does not change from page to page is kept out of the
page response and served once per language by `?type=834`:

* `navigation` — the page tree from the site root, seven levels
  (:ref:`MenuProcessor <dataprocessors-menuprocessor>` items).
* `i18n` — the language list, identical to the one in the page response.
* `user.logged` — `true` for a logged-in frontend user; the key is absent
  otherwise.
* `backendEditor` — for a logged-in backend user, the URLs of the backend
  edit forms for the current page and its records.

The request goes to any page URL of the site with `?type=834`; the language
prefix selects the language (`/de/?type=834`). The response is cached like a
page. Login state is evaluated in a TypoScript condition, so anonymous and
logged-in visitors get separate cache entries. On a `headless: 2` site the
request needs the JSON `Accept` header like every other one. Requests for this
type skip shortcut and mount-point redirects, so a site root configured as a
shortcut still delivers the data.

nuxt-typo3 loads it before the first page render (`features.initInitialData`)
from `typo3.api.endpoints.initialData` (default `/?type=834`), prefixed with
the current locale path, and keeps it in `useT3Api().initialData`. It is
reloaded when `useT3i18n().setLocale()` switches the language, and refetched
with `endpoints.initialDataFallback` when the localised request fails, so the
error page still has a navigation. `getInitialData(path)` fetches it manually.

`initialData` is a page object copied from `lib.headlessPage`, so the site
package extends it through `initialData.10.fields`. Anything a layout needs on
every page belongs here, for example a footer menu from a folder and the
site name:

.. code-block:: typoscript

   initialData.10.fields {
     siteName = TEXT
     siteName.data = site:websiteTitle

     footerNavigation = JSON
     footerNavigation {
       dataProcessing {
         10 = headless-menu
         10 {
           special = directory
           special.value = 15
           as = footerNavigation
         }
       }
     }
   }

The new keys appear on `initialData.value` in nuxt-typo3; extend its
`T3InitialData` interface in the project for typed access. Page-specific
data does not belong here: it would be stale until the next initial data
request.

Rendering content
=================

`content` is grouped by `colPos`, so the frontend needs one component per
backend layout that places the columns, and one component per content element
`type`. nuxt-typo3 resolves both by name:

* `appearance.backendLayout: "singlecolumn"` → `T3BlSinglecolumn`. The
  component receives `content` and renders each column with `T3Renderer`.
* Every element in a column → `T3Ce<Type>` with the `type` converted from
  snake_case to PascalCase: `text` → `T3CeText`,
  `image_with_description` → `T3CeImageWithDescription`. The keys of the
  element's `content` object become props, together with `id`, `type` and
  `appearance` (`T3CeBaseProps`). Types without a component fall back to the
  `Default` component.

.. code-block:: vue

   <!-- components/global/T3BlSinglecolumn.vue -->
   <template>
     <div>
       <T3Renderer v-if="content?.colPos0" :content="content.colPos0" />
       <aside>
         <T3Renderer v-if="content?.colPos1" :content="content.colPos1" />
       </aside>
     </div>
   </template>

   <script lang="ts" setup>
   import type { T3BackendLayout } from '@t3headless/nuxt-typo3'
   defineProps<T3BackendLayout>()
   </script>

A custom content element registered in TYPO3 as `image_with_description`
(:ref:`developer-custom-contentelements`) with `bodytext` and `assets` fields
is rendered by:

.. code-block:: vue

   <!-- components/global/T3CeImageWithDescription.vue -->
   <template>
     <h2>{{ header }}</h2>
     <T3HtmlParser :content="bodytext" />
     <img v-if="assets?.[0]" :src="assets[0].publicUrl" :alt="assets[0].properties.alternative">
   </template>

   <script setup lang="ts">
   import type { T3CeBaseProps, T3File } from '@t3headless/nuxt-typo3'

   interface T3CeImageWithDescription extends T3CeBaseProps {
     bodytext: string
     assets?: T3File[]
   }
   withDefaults(defineProps<T3CeImageWithDescription>(), { bodytext: '' })
   </script>

RTE fields (`bodytext` of `text`, `textpic`, `textmedia`) contain HTML. Links
in it already point at `frontendBase`; `T3HtmlParser` renders the HTML and
turns internal links into router links. Shipped components can be replaced by
placing a component with the same name in `components/global`
(:ref:`ref-content-elements` lists every shipped `type` and its props).

Menus and navigation
====================

Menus come from four places, most common first:

* **Site navigation** — `navigation` in `?type=834` (:ref:`requests`): the
  page tree from the site root, seven levels, as
  :ref:`MenuProcessor <dataprocessors-menuprocessor>` items. Fetch it once per
  language, not per page. nuxt-typo3 loads it on startup
  (`features.initInitialData`), reloads it after a language switch and exposes
  it as `initialData`:

  .. code-block:: vue

     <!-- layouts/default.vue -->
     <template>
       <div>
         <header v-if="navigation">
           <NuxtLink v-for="{ link, title } in navigation" :key="link" :to="link">
             {{ title }}
           </NuxtLink>
         </header>
         <slot />
       </div>
     </template>

     <script setup lang="ts">
     const { initialData } = useT3Api()
     const navigation = computed(() => initialData.value?.navigation?.[0]?.children)
     </script>

  `navigation[0]` is the site root; its `children` are the first menu level.
  `link` values are frontend URLs once `frontendBase` is set, so they can be
  passed to the router directly.
* **Breadcrumbs** — `breadcrumbs` on every page response (`lib.breadcrumbs`),
  same item shape.
* **Menu content elements** — `menu_subpages`, `menu_section`, `menu_sitemap`
  and the others render like any content element; their `content.menu`
  holds the items (:ref:`ref-content-elements`).
* **Custom menus** — a field with the `headless-menu` processor on the page
  response (`page.10.fields`) or, for site-wide menus, on the initial data
  (`initialData.10.fields`, example in :ref:`frontend-initial-data`).

Languages
=========

`i18n` on every page response and in `?type=834` lists the site languages with
`link`, `active`, `current` and `available`. A language switcher renders those
links; switching languages is a navigation to the other language's URL,
because TYPO3 resolves the language from the path. nuxt-typo3 detects the
language from the URL prefix using `typo3.i18n.locales`, so that list has to
match the language `base` paths of the site (`['en', 'de']` for `/en/` and
`/de/`).
`useT3i18n()` provides `currentLocale`, `setLocale()` (which re-fetches
`initialData`) and `getPathWithLocale()`.

Head data
=========

`seo` carries everything for the document head. `useT3Page()` and
`useT3Meta()` return it as `headData` in the shape `useHead()` expects; the
composable also splits it into `base`, `opengraph` and `twitter` for custom
head handling.

Forms and login
===============

`form_formframework` elements carry the form definition in `content.form`
and the submit URL in `content.link` (:ref:`integrations-form`). nuxt-typo3
renders it with `T3CeFormFormframework`, which wraps `T3Form`, resolves a
field component per `type`/`identifier` (`T3FormFieldText`, …) and posts the
values form-encoded to `link`; the `api` object of the response drives error
and success handling. Login (`felogin_login`) works the same way on
`content.data` (:ref:`integrations-felogin`). Both need the TYPO3 cookies:
`typo3.api.credentials: 'include'` for browser requests and the
`proxyReqHeaders`/`proxyHeaders` options for server-side rendering
(:ref:`requests`).

Errors
======

For a missing page TYPO3 returns the response of its site error handling
with the matching status code. nuxt-typo3 turns a non-2xx API response into
a Nuxt error; the `error.vue` page can render TYPO3's error page content
through `useT3Page({ fetchOnInit: false })` and its `pageDataFallback`.

.. _frontend-multisite:

Multiple sites and `?type=835`
==============================

`?type=835` (`headless_domains`) lists the sites of the TYPO3 instance that
have `headless` enabled, one entry per site:

.. code-block:: json

   [
     {
       "name": "example.com",
       "baseURL": "https://example.com",
       "api": { "baseURL": "https://example.com/headless" },
       "i18n": { "locales": ["default", "de"], "defaultLocale": "default" }
     }
   ]

`baseURL` is the site's `frontendBase`, `api.baseURL` its `frontendApiProxy`,
`locales` the `typo3Language` keys. A frontend that serves several TYPO3
sites from one deployment uses it to map the request hostname to the API it
has to call.

nuxt-typo3 does not call it at runtime. Its multi-site setup is static:
`typo3.sites` holds one entry per hostname, matched against the incoming
request, each with its own `api` and `i18n` settings:

.. code-block:: ts

   export default defineNuxtConfig({
     typo3: {
       sites: [
         {
           hostname: ['example.com', 'www.example.com'],
           api: { baseUrl: 'https://api.example.com' },
           i18n: { default: 'en', locales: ['en', 'de'] },
         },
         {
           hostname: 'example.de',
           api: { baseUrl: 'https://api.example.de' },
           i18n: { default: 'de', locales: ['de'] },
         },
       ],
     },
   })

The array can be generated from `?type=835` at build time, so a site added
in TYPO3 needs no change in the frontend repository; `nuxt.config.ts` is an
ES module and can await the request:

.. code-block:: ts

   import { ofetch } from 'ofetch'

   const domains = await ofetch<T3Domain[]>(`${process.env.TYPO3_API}/?type=835`)

   export default defineNuxtConfig({
     typo3: {
       sites: domains.map((domain) => ({
         hostname: domain.name,
         api: { baseUrl: domain.api.baseURL },
         i18n: {
           default: domain.languages[0].base,
           locales: domain.languages.map((language) => language.base),
         },
       })),
     },
   })

The example reads `languages` rather than `i18n.locales`: nuxt-typo3 detects
the language from the URL prefix (`de`), while `locales` are TYPO3's
`typo3Language` keys (`default`, `de`). That mismatch is the usual reason to
extend the endpoint.

Extending the output
--------------------

`headless_domains` is a normal page object; its processor configuration can
be changed from the site package (loaded after the set):

* `allowedSites` (comma list of root page uids) or `sitesFromPid` (a
  page/separator whose subpages are the site roots) restrict which sites are
  listed; `sortingField` orders them by a `pages` column.
* `siteSchema` replaces the class that shapes each entry. Extend the shipped
  `DomainSchema` to add fields; it receives the `Site` objects through the
  provider:

  .. code-block:: php

     namespace Vendor\SitePackage\Headless;

     use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
     use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

     class DomainSchema extends \FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\DomainSchema
     {
         public function process(SiteProviderInterface $provider, array $options = []): array
         {
             $result = parent::process($provider, $options);

             foreach (array_values($provider->getSites()) as $index => $site) {
                 $result[$index]['languages'] = array_map(
                     static fn (SiteLanguage $language): array => [
                         'locale' => $language->getTypo3Language(),
                         'base' => trim($language->getBase()->getPath(), '/') ?: 'en',
                         'hreflang' => $language->getHreflang(),
                     ],
                     array_values($site->getLanguages()),
                 );
             }

             return $result;
         }
     }

  .. code-block:: typoscript

     headless_domains.10.dataProcessing.10.siteSchema = Vendor\SitePackage\Headless\DomainSchema

  The class is instantiated with `GeneralUtility::makeInstance()`, so
  constructor injection works as long as the class is known to the
  container (autoconfigured in the site package's `Services.yaml`).
* `siteProvider` replaces the discovery itself (implement
  :php:`FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface`),
  for example to list sites without the `headless` flag.
* `dataProcessing.` on the processor runs further data processors per entry,
  with the entry itself as data. It cannot reach the `Site` object, so the
  schema class is the right place for site-derived fields.
* `dbColumns` and `titleField` choose the `pages` columns loaded for each root
  page and the one the shipped `SiteSchema` (the default when `siteSchema` is
  empty) uses as `title`; `DomainSchema` ignores them.

The same processor drives the `RootSitesProcessor` entry in
:ref:`dataprocessors-rootsiteprocessor`; the options are identical.
