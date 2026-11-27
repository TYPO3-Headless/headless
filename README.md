# TYPO3 Extension `headless` - JSON Content API for TYPO3 Headless solution

[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)
[![CI Status](https://github.com/TYPO3-Headless/headless/workflows/CI/badge.svg)](https://github.com/TYPO3-Headless/headless/actions)
[![Latest Stable Version](https://poser.pugx.org/friendsoftypo3/headless/v)](https://packagist.org/packages/friendsoftypo3/headless)
[![Total Downloads](https://poser.pugx.org/friendsoftypo3/headless/downloads)](https://packagist.org/packages/friendsoftypo3/headless)
[![License](https://poser.pugx.org/friendsoftypo3/headless/license)](https://packagist.org/packages/friendsoftypo3/headless)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg)](CODE_OF_CONDUCT.md)
[![Coverage Status](https://coveralls.io/repos/github/TYPO3-Headless/headless/badge.svg?branch=master)](https://coveralls.io/github/TYPO3-Headless/headless?branch=master)

[![Headless](https://github.com/TYPO3-Headless/.github/blob/main/profile/headless.jpeg?raw=true)](https://extensions.typo3.org/extension/headless)

Headless renders TYPO3 pages and content as JSON. You decide which fields are output, how they are named and nested, and what types they have, using plain TypoScript.

The extension is the backend part (JSON API) of the TYPO3 PWA solution. The frontend part is [nuxt-typo3](https://github.com/TYPO3-Headless/nuxt-typo3), a Vue.js/Nuxt application that consumes the API. Its documentation lives at https://t3headless.macopedia.io/nuxt-typo3. The JSON API works with any other frontend as well.

|                  | URL                                                          |
|------------------|--------------------------------------------------------------|
| **Repository:**  | https://github.com/TYPO3-Headless/headless                   |
| **Read online:** | https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/ |
| **TER:**         | https://extensions.typo3.org/extension/headless/             |
| **Slack:**       | https://typo3.slack.com/channels/initiative-headless-pwa     |

## Features

- JSON API for pages, page metadata (SEO), content elements, menus and layouts
- respects the site's language and translation configuration (including fallbacks)
- `JSON`, `CONTENT_JSON`, `INT`, `FLOAT` and `BOOL` content objects for typed, nested output
- headless-aware data processors (menus, files and images, galleries, FlexForms, database queries, root sites)
- full headless mode or mixed mode (Fluid HTML and JSON from the same site)
- separate frontend, API and file URLs per site, language and base variant
- installed core extensions are integrated automatically: EXT:form, EXT:felogin, EXT:redirects, EXT:seo
- workspace and page preview support
- easy to extend with custom fields, content elements and processors

### Additional extensions and integrations

- EXT:news: [headless_news](https://github.com/TYPO3-Headless/headless_news)
- EXT:solr: [headless_solr](https://github.com/TYPO3-Headless/headless_solr)
- EXT:powermail: [headless_powermail](https://github.com/TYPO3-Headless/headless_powermail)
- EXT:container: [headless-container-support](https://github.com/TYPO3-Headless/headless-container-support) and [headless_container](https://github.com/itplusx/headless-container)
- EXT:gridelements: [headless_gridelements](https://github.com/itplusx/headless_gridelements)
- local development tweaks: [headless_dev_tools](https://github.com/TYPO3-Headless/headless_dev_tools)

Check each extension for the TYPO3 and EXT:headless versions it supports.

## Requirements and compatibility

| EXT:headless | Branch   | TYPO3      | PHP              | Status                      |
|--------------|----------|------------|------------------|-----------------------------|
| `5.x`        | `master` | `14`       | `>= 8.2`         | Active development & support |
| `4.x`        | `4.x`    | `12`, `13` | `>= 8.2`         | Bug & security fixes        |
| `3.x`        | `3.x`    | `11`       | `>= 7.4, <= 8.2` | End of life                 |
| `2.x`        | `2.x`    | `9`, `10`  | `>= 7.2, <= 7.4` | End of life                 |

Upgrading from 4.x? Read [UPGRADE.md](UPGRADE.md) first. The default response changed in 5.0, and the `friendsoftypo3/headless-legacy` site set keeps the 4.x output.

## Installation

Install the extension using Composer:

```bash
composer require friendsoftypo3/headless
```

Composer installation is recommended. In classic mode, unpack the release from TER into `typo3conf/ext/headless`.

## Quickstart / Demo

For a working demo with frontend, backend and demo data, use the DDEV based demo project:
[https://github.com/TYPO3-Headless/pwa-demo](https://github.com/TYPO3-Headless/pwa-demo)

### How to start with TYPO3 Headless (video tutorial)

An introduction to TYPO3 Headless for developers, content managers and anyone curious about it:

[![video still](https://i.ytimg.com/vi/7MOwugAyHkY/hq720.jpg)](https://www.youtube.com/watch?v=7MOwugAyHkY)

## Configuration

Each site decides how it responds via two keys in its `config/sites/<identifier>/config.yaml`:

```yaml
dependencies:
  - friendsoftypo3/headless
headless: 1
```

### Site set (`dependencies`): pick one

| Site set                         | Static include (sys_template)       | Response                                                                                          |
|----------------------------------|-------------------------------------|---------------------------------------------------------------------------------------------------|
| `friendsoftypo3/headless`        | Headless                            | Trimmed default response (new projects)                                                           |
| `friendsoftypo3/headless-legacy` | Headless Legacy (4.x)               | Full 4.x-compatible response (upgrades)                                                           |
| `friendsoftypo3/headless-mixed`  | Headless - Mixed mode JSON response | Full 4.x-compatible response, served as JSON only for requests whose `Accept` header is exactly `application/json` |

Do not add a root sys_template record to a site using sets. Its "Clear" flags would wipe the TypoScript provided by the set. Sites without sets can include the matching static template in their root sys_template record instead.

### Run mode (`headless`)

Set it in the YAML file or in the Sites module:

- **0**: headless mode is deactivated for the site. **Default value.**
- **1**: headless mode is fully activated for the site.
- **2**: mixed mode. Fluid and the JSON API are both available within the site. Pair it with the mixed set.

In mixed mode TYPO3 responds with JSON only when the `Accept` header is exactly `application/json`. A list like `Accept: application/json, text/plain, */*` (the axios/fetch default) or `application/json; charset=utf-8` gets the standard HTML response.

The legacy boolean values (`true`/`false`) still work because the flag is cast to an integer, but the integer notation is recommended.

### Frontend and API URLs

If your frontend runs on a different domain than TYPO3, tell headless where it lives so links, preview URLs and file URLs in the response point to the right host:

```yaml
base: https://api.example.com/
frontendBase: https://example.com/
```

`frontendApiProxy`, `frontendFileApi` and `frontendFileApiByStorage` (per file storage) cover API and file proxies. All of them can be overridden per base variant and per language. See [Multi-site and URLs](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Configuration/MultiSite.html).

Feature flags and the rest of the options are described in the [Configuration chapter](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Configuration/Index.html).

## JSON Content Object

The `JSON` content object lets you specify which fields to output and how they look:

```typoscript
lib.page = JSON
lib.page {
  fields {
    header = TEXT
    header {
      field = header
    }
  }
}
```

Output:

```json
{
  "header": "headerFieldValue"
}
```

Fields can be nested with another `fields` block:

```typoscript
lib.page = JSON
lib.page {
  fields {
    data {
      fields {
        foo = TEXT
        foo {
          field = bar
        }

        foo1 = TEXT
        foo1 {
          field = bar1
        }
      }
    }
  }
}
```

Output:

```json
{
  "data": {
    "foo": "bar",
    "foo1": "bar1"
  }
}
```

Every field accepts any TypoScript content object (`TEXT`, `COA`, `CASE`, `USER`, ...), so you can override every part of the output.

### INT, FLOAT & BOOL Content Objects

These content objects return real numbers and booleans, so the frontend does not have to deal with string values for numeric or true/false fields. They only work as fields inside a `JSON` content object.

```typoscript
lib.page = JSON
lib.page {
  fields {
    data {
      fields {
        foo = INT
        foo {
          # db value of foo_field = 1
          field = foo_field
        }
        bar = BOOL
        bar {
          # db value of bar_field = 0
          field = bar_field
        }
      }
    }
  }
}
```

Output:

```json
{
  "data": {
    "foo": 1,
    "bar": false
  }
}
```

`CONTENT_JSON` renders content elements grouped by column (`colPos`). All properties are described in the [TypoScript cObjects chapter](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Developer/CObjects.html).

## Data processing

You can use data processors just like in the `FLUIDTEMPLATE` content object:

```typoscript
lib.languages = JSON
lib.languages {
  dataProcessing {
    10 = headless-language-menu
    10 {
      languages = auto
      as = languages
    }
  }
}
```

By default the processor result replaces the `fields` output of the object. Set `dataProcessingMerge = 1` to keep both:

```typoscript
lib.page = JSON
lib.page {
  dataProcessingMerge = 1
  fields {
    title = TEXT
    title.field = title
  }
  dataProcessing {
    10 = headless-menu
    10.as = mainMenu
  }
}
```

### Built-in data processors

Each processor is registered under a short identifier. The class name (namespace `FriendsOfTYPO3\Headless\DataProcessing`) works as well.

| Identifier                  | Processor                  | Purpose                                                                 |
|-----------------------------|----------------------------|-------------------------------------------------------------------------|
| `headless-menu`             | `MenuProcessor`            | Navigation; works like the core menu processor                          |
| `headless-language-menu`    | `LanguageMenuProcessor`    | Language switcher                                                       |
| `headless-files`            | `FilesProcessor`           | Files and images, including processing and crop variants ([Images](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Developer/Images.html)) |
| `headless-gallery`          | `GalleryProcessor`         | Media galleries; chain it after `headless-files`                        |
| `headless-database-query`   | `DatabaseQueryProcessor`   | Records from the database                                               |
| `headless-flex-form`        | `FlexFormProcessor`        | FlexForm fields                                                         |
| `headless-extract-property` | `ExtractPropertyProcessor` | Picks a single property from processed data                             |
| `headless-root-sites`       | `RootSitesProcessor`       | Configuration of all headless sites for your frontend application       |

Details and options: [Data processors](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Developer/DataProcessors.html).

## Example page response ⬇️

![image](https://user-images.githubusercontent.com/15106746/136414744-88d54d44-2f3c-4d7d-9911-832ceefcfe16.png)

## Documentation

The [extension documentation](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Index.html) covers configuration, custom content elements, images, caching, events, integrations (EXT:form, EXT:felogin, EXT:redirects) and a FAQ.

## Contributing

Contributions are welcome. See [CONTRIBUTING.rst](CONTRIBUTING.rst) and the [Code of Conduct](CODE_OF_CONDUCT.md).

![Alt](https://repobeats.axiom.co/api/embed/197db91cad9195bb15a06c91fda5a215bff26cba.svg)

## Development

This extension is developed as part of the [TYPO3 PWA initiative](https://typo3.org/community/teams/typo3-development/initiatives/pwa/).
If you have any questions, join the #initiative-headless-pwa Slack channel.

## Credits

Special thanks to [macopedia.com](https://macopedia.com), which sponsors the development of this solution.
