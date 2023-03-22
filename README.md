# TYPO3 Extension `headless` - JSON Content API for TYPO3 Headless solution

[![TYPO3 10](https://img.shields.io/badge/TYPO3-10-orange.svg)](https://get.typo3.org/version/10)
[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![CI Status](https://github.com/TYPO3-Initiatives/headless/workflows/CI/badge.svg)](https://github.com/TYPO3-Initiatives/headless/actions)
[![Latest Stable Version](https://poser.pugx.org/friendsoftypo3/headless/v)](//packagist.org/packages/friendsoftypo3/headless)
[![Total Downloads](https://poser.pugx.org/friendsoftypo3/headless/downloads)](//packagist.org/packages/friendsoftypo3/headless)
[![License](https://poser.pugx.org/friendsoftypo3/headless/license)](//packagist.org/packages/friendsoftypo3/headless)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg)](CODE_OF_CONDUCT.md)
[![Coverage Status](https://coveralls.io/repos/github/TYPO3-Headless/headless/badge.svg?branch=master)](https://coveralls.io/github/TYPO3-Headless/headless/badge.svg?branch=master)

[![Headless](https://github.com/TYPO3-Headless/.github/blob/main/profile/headless.jpeg?raw=true)](https://extensions.typo3.org/extension/headless)

Headless allows you to render JSON from TYPO3 content. You can customize output by changing types, names and nesting of fields.

This extension provides backend part (JSON API) for TYPO3 PWA solution. Second part is a JavaScript application [nuxt-typo3](https://github.com/TYPO3-Headless/nuxt-typo3) which consumes JSON API and renders the content using Vue.js and Nuxt. See frontend documentation here: https://typo3-headless.github.io/nuxt-typo3/

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
- [BETA] backend module for simulating page preview (with specific page type, lang, usergroup)

### Additional extensions and integrations

- headless support for EXT:news [headless_news](https://github.com/TYPO3-Initiatives/headless_news)
- headless support for EXT:solr [headless_solr](https://github.com/TYPO3-Initiatives/headless_solr)
- headless support for EXT:powermail [headless_powermail](https://github.com/TYPO3-Initiatives/headless_powermail)
- headless support for EXT:gridelements [headless_gridelements](https://github.com/itplusx/headless_gridelements)

## Requirements and compatibility
With the release of TYPO3 v11.5 LTS we have to move support for TYPO3 v9 and v10 to another branch as changes between those two versions are incompatible. Version 3.x and master branch will support TYPO3 v11, and headless version 2.x keep support for v9 and v10.

#### Headless version 3.x
|   	|  PHP 7.2	| PHP 7.3   |  PHP 7.4 	|  PHP 8.0  	|
|---	|---	|---	|---	|---	|
|  TYPO3 v9.5  	|   no 	|   no 	|   no	|   no	|
|  TYPO3 v10.4	|   no	|   no	|   no	|   no	|
|  TYPO3 v11.5 	|   no	|   no	|   yes	|   yes	|

#### Headless version 2.x
|   	|  PHP 7.2	| PHP 7.3   |  PHP 7.4 	|  PHP 8.0  	|
|---	|---	|---	|---	|---	|
|  TYPO3 v9.5  	|   yes 	|   yes 	|   yes	|   no	|
|  TYPO3 v10.4	|   yes	|   yes	|   yes	|   no	|
|  TYPO3 v11.5 	|   no	|   no	|   no	|   no	|
## Quickstart / Demo

If you want to take a look at working demo including frontend, backend and demo data, use our DDEV based demo project here:
https://github.com/TYPO3-Initiatives/pwa-demo

## Installation
Install extension using composer\
``composer require friendsoftypo3/headless``

Then, you should include extension typoscript template, and you are ready to go. Also, please remember to don't use fluid styled content on the same page tree together with ext:headless.

## Documentation
[Extension documentation](https://docs.typo3.org/p/friendsoftypo3/headless/master/en-us/Index.html)

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

## Page response
In headless v3.0 we introduce a new, smaller, faster and more flat page response.
If you want to keep compatibility with your frontend application, you can load a deprecated typoscript template for version 2.x and keep the old structure of the response running.
#### New response (version 3.x) ⬇️
![image](https://user-images.githubusercontent.com/15106746/136414744-88d54d44-2f3c-4d7d-9911-832ceefcfe16.png)

#### Old response (version 2.x) ⬇️
![image](https://user-images.githubusercontent.com/15106746/136414370-a4bec856-5a95-4965-b60b-5a37be5ce5c9.png)

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
We provide multiple data processorors for headless usage

### FilesProcessor
This processor should be used to process files (standard or media files).
Also, it allows you to proccess images.
### GalleryProcessor
Should be used along with `FilesProcessor` (chained). Used for processing mutliple
media files.
### MenuProcessor
Used for navigation. Works just like standard menu processor.
### FlexFormProcessor
Used for proecessing flexforms.
### RootSitesProcessor
Render your all headless sites configuration for your frontend application.

## Development
Development for this extension is happening as part of the TYPO3 PWA initiative, see https://typo3.org/community/teams/typo3-development/initiatives/pwa/
If you have any questions, join #initiative-pwa Slack channel.

## Credits

A special thanks goes to [macopedia.com](https://macopedia.com) company, which is sponsoring development of this solution.

### Developers involved in the project

- Łukasz Uznański (Macopedia)
- Adam Marcinkowski (Macopedia)
- Vaclav Janoch (ITplusX)
