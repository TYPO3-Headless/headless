# TYPO3 Extension `headless` - JSON Content API for TYPO3 Headless solution

[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![CI Status](https://github.com/TYPO3-Headless/headless/workflows/CI/badge.svg)](https://github.com/TYPO3-Headless/headless/actions)
[![Latest Stable Version](https://poser.pugx.org/friendsoftypo3/headless/v)](//packagist.org/packages/friendsoftypo3/headless)
[![Total Downloads](https://poser.pugx.org/friendsoftypo3/headless/downloads)](//packagist.org/packages/friendsoftypo3/headless)
[![License](https://poser.pugx.org/friendsoftypo3/headless/license)](//packagist.org/packages/friendsoftypo3/headless)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg)](CODE_OF_CONDUCT.md)
[![Coverage Status](https://coveralls.io/repos/github/TYPO3-Headless/headless/badge.svg?branch=master)](https://coveralls.io/github/TYPO3-Headless/headless/badge.svg?branch=master)

[![Headless](https://github.com/TYPO3-Headless/.github/blob/main/profile/headless.jpeg?raw=true)](https://extensions.typo3.org/extension/headless)

Headless allows you to render JSON from TYPO3 content. You can customize output by changing types, names and nesting of fields.

This extension provides backend part (JSON API) for TYPO3 PWA solution. Second part is a JavaScript application [nuxt-typo3](https://github.com/TYPO3-Headless/nuxt-typo3) which consumes JSON API and renders the content using Vue.js and Nuxt. See frontend documentation here: https://t3headless.macopedia.io/nuxt-typo3

If you have any questions just drop a line in #initiative-headless-pwa Slack channel.

|                  | URL                                                               |
|------------------|-------------------------------------------------------------------|
| **Repository:**  | https://github.com/TYPO3-Headless/headless                        |
| **Read online:** | https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/      |
| **TER:**         | https://extensions.typo3.org/extension/headless/                  |
| **Slack:**       | https://typo3.slack.com/channels/initiative-headless-pwa          |

## Features

- JSON API for content elements
- JSON API for page and meta data
- JSON API for navigation, layouts
- taking into account all language and translation configuration (e.g. fallback)
- easily extendable with custom fields or custom content elements
- custom data processors directly for headless usage
- support for EXT:form
- support for EXT:felogin
- support for EXT:redirects
- support for EXT:seo

### Additional extensions and integrations

- headless support for EXT:news [headless_news](https://github.com/TYPO3-Initiatives/headless_news)
- headless support for EXT:solr [headless_solr](https://github.com/TYPO3-Initiatives/headless_solr)
- headless support for EXT:powermail [headless_powermail](https://github.com/TYPO3-Initiatives/headless_powermail)
- headless support for EXT:gridelements [headless_gridelements](https://github.com/itplusx/headless_gridelements)
- small tools/tweaks for local headless development [headless_dev_tools](https://github.com/TYPO3-Headless/headless_dev_tools)
- headless support for EXT:container [headless-container-support](https://github.com/TYPO3-Headless/headless-container-support) and [headless_container](https://github.com/itplusx/headless-container)

## Requirements and compatibility

| EXT:headless version  	 | TYPO3 support | PHP support       | Status 	                      |
|-------------------------|---------------|-------------------|-------------------------------|
| `>= 5.0`  	             | `14` 	        | `>= 8.2`  	       | Active development & support	 |
| `>= 4.0`  	             | `12`, `13` 	  | `>= 8.2`  	       | Bug & security fixes	         |
| `>= 3.0`                | `11`	         | `>= 7.4, <= 8.2`	 | End of life 	                 |
| `>= 2.0` 	              | `9`, `10`	    | `>= 7.2, <=7.4`	  | End of life	                  |


## Quickstart / Demo

If you want to take a look at working demo including frontend, backend and demo data, use our DDEV based demo project here:
[https://github.com/TYPO3-Headless/pwa-demo](https://github.com/TYPO3-Headless/pwa-demo)

## Installation
Install extension using composer

``composer require friendsoftypo3/headless``

## Documentation
[Extension documentation](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Index.html)

## How to start with TYPO3 Headless video tutorial

Whether you are a developer, content manager, or a tech enthusiast, this tutorial is tailored to provide a comprehensive introduction to TYPO3 Headless, helping you to get started on your journey with confidence.

[![video still](https://i.ytimg.com/vi/7MOwugAyHkY/hq720.jpg)](https://www.youtube.com/watch?v=7MOwugAyHkY)

## Configuration

Each site decides how it responds via two keys in its `config/sites/<identifier>/config.yaml`:

```yaml
dependencies:
  - friendsoftypo3/headless
headless: 1
```

### Site set (`dependencies`) — pick one:

- `friendsoftypo3/headless` — trimmed default response (new projects)
- `friendsoftypo3/headless-legacy` — full 4.x-compatible response (upgrades)
- `friendsoftypo3/headless-mixed` — full 4.x-compatible response, served as JSON only for requests whose `Accept` header is exactly `application/json`

Do not add a root sys_template record to a site using sets — its "Clear" flags would wipe the set TypoScript.

### Run mode (`headless`) — set in the yaml file or via the Site's management backend:

- **0** = headless mode is deactivated for the site within the TYPO3 instance. **Default value!**
- **1** = headless mode is fully activated for the site within the TYPO3 instance.
- **2** = mixed mode headless is activated (both fluid & json API are accessible within a single site in the TYPO3 instance) — pair it with the mixed set.

In mixed mode, TYPO3 responds with JSON only when the first `Accept` header value is exactly `application/json`. A list like `Accept: application/json, text/plain, */*` (the axios/fetch default) or `application/json; charset=utf-8` gets the standard HTML response.

The legacy boolean values (`true`|`false`) are still recognized (the flag is cast to integer), but the integer notation is recommended. Full details, feature flags and multi-domain URL handling live in the [Configuration chapter](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Configuration/Index.html).

## JSON  Content Object
In headless extension we implemented new JSON Content Object, which allows you to specify what fields you want to output, and how they will look. First, let's take a look at simple example
```
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
Output
```
{
    "header" : "headerFieldValue"
}
```
in addition, keyword `fields` allow you to nest multiple times fields in json, e.g.

```
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
Output
```
{
    "data": [
        {
            "foo": "bar",
            "foo1": "bar1"
        }
    ]
}
```
## INT, FLOAT & BOOL Content Objects for use in JSON Content Object

We introduce new simple content objects to improve JSON API response for frontend developers.
We can set correct property types, so frontend does not have to deal with string values for fields with numeric values or field that should be true/false.
```
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
Output
```
{
    "data": [
        {
            "foo": 1,
            "bar": false
        }
    ]
}
```
## Customizing
You can override every field in output using typoscript. This extension allows you to use standard typoscript objects such as TEXT, COA, CASE.

## Example page response  ⬇️

![image](https://user-images.githubusercontent.com/15106746/136414744-88d54d44-2f3c-4d7d-9911-832ceefcfe16.png)

## DataProcessing

You can use Data Processors just like in `FLUIDTEMPLATE` Content Object, e.g.

```
lib.languages = JSON
lib.languages {
  dataProcessing {
    10 = TYPO3\CMS\Frontend\DataProcessing\LanguageMenuProcessor
    10 {
      languages = auto
      as = languages
    }
  }
}
```

## Built in data processors

We provide multiple data processors for headless rendering purposes.

### DatabaseQueryProcessor
Used for fetching records from the database.

### FilesProcessor
This processor should be used to process files (standard or media files).

Also, it allows you to process images. See [docs chapter: Images](https://docs.typo3.org/p/friendsoftypo3/headless/main/en-us/Developer/Images.html) for details.

### GalleryProcessor
Should be used along with `FilesProcessor` (chained). Used for processing mutliple media files.

### MenuProcessor
Used for navigation. Works just like standard menu processor.

### FlexFormProcessor
Used for processing flexforms.

### RootSitesProcessor
Render your all headless sites configuration for your frontend application.

## Contributing
![Alt](https://repobeats.axiom.co/api/embed/197db91cad9195bb15a06c91fda5a215bff26cba.svg)

## Development

Development for this extension is happening as part of the TYPO3 PWA initiative, see https://typo3.org/community/teams/typo3-development/initiatives/pwa/
If you have any questions, join the #initiative-headless-pwa Slack channel.

## Credits

A special thanks goes to [macopedia.com](https://macopedia.com) company, which is sponsoring development of this solution.

