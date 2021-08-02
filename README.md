# TYPO3 Extension "headless" - JSON content API for TYPO3 PWA solution

[![CI Status](https://github.com/TYPO3-Initiatives/headless/workflows/CI/badge.svg)](https://github.com/TYPO3-Initiatives/headless/actions)
[![Latest Stable Version](https://poser.pugx.org/friendsoftypo3/headless/v)](//packagist.org/packages/friendsoftypo3/headless)
[![Total Downloads](https://poser.pugx.org/friendsoftypo3/headless/downloads)](//packagist.org/packages/friendsoftypo3/headless)
[![License](https://poser.pugx.org/friendsoftypo3/headless/license)](//packagist.org/packages/friendsoftypo3/headless)

Headless allows you to render JSON from TYPO3 content. You can customize output by changing types, names and nesting of fields.

This extension provides backend part (JSON API) for TYPO3 PWA solution. Second part is a JavaScript application [nuxt-typo3](https://github.com/TYPO3-Initiatives/nuxt-typo3) which consumes JSON API and renders the content using Vue.js and Nuxt. See frontend documentation here: https://typo3-initiatives.github.io/nuxt-typo3/

If you have any questions just drop a line in #initiative-pwa Slack channel.

## Features

- JSON API for content elements
- JSON API for navigation, layouts
- taking into account all language/translation configuration (e.g. fallback)
- easily extensible with custom fields or custom CE's
- basic support for EXT:form
- [BETA] backend module for simulating page preview (with specific page type, lang, usergroup)
- support for felogin (comming soon)

### Additional extensions and integrations

- headless support for EXT:news https://github.com/TYPO3-Initiatives/headless_news
- headless support for EXT:solr https://github.com/TYPO3-Initiatives/headless_solr
- headless support for EXT:powermail https://github.com/TYPO3-Initiatives/headless_powermail
- headless support for EXT:gridelements https://github.com/itplusx/headless_gridelements

## Requirements
Extension requires TYPO3 in version at least 9.5.

## Quickstart / Demo

If you want to take a look at working demo including frontend, backend and demo data, use our DDEV based demo project here:
https://github.com/TYPO3-Initiatives/pwa-demo

## Installation
Install extension using composer\
``composer require friendsoftypo3/headless``

Then, you should include extension typoscript template, and you are ready to go. Also, please remember to don't use fluid styled content on the same page tree together with ext:headless.

## Documentation
[Documentation](https://docs.typo3.org/p/friendsoftypo3/headless/master/en-us/Index.html)

## JSON  Content Object
In headless extension we implemented new JSON Content Object, which allows you to specify what fields you want to output, and how they will look. First of all, let's take a look at simple example
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
## INT & BOOL Content Objects for use in JSON Content Object

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
### Customizing
You can override every field in output using typoscript. This extension allows you to use standard typoscript objects such as TEXT, COA, CASE.

### DataProcessing
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

### Available features toggles

To change the setting for this extension feature either use Localconfiguration.php: or AdditionalConfiguration.php:

**headless.frontendUrls** or **~~FrontendBaseUrlInPagePreview~~** (deprecated)

This feature toggle extends current SiteConfiguration (and it's variants) with new field for Frontend Url
(url frontend of PWA app). This new field is used when there is a need to preview a page such as: "view" module or right click on a page + show, or the 'eye' icon in page view
& allow generating proper cross-domain links for headless instance.
```
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;
```

**headless.storageProxy**

Enable ability to set storage proxy in site configuration (and it's variants) & serve files via proxy from same domain

Feature flag requires TYPO3 >= 10.4.10

*WARNING* if you install `TYPO3 >= 10.4.18` please update also `ext:headless` to version `>= 2.5.3`

```
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;
```

**headless.redirectMiddlewares**

Enable new & replace core middlewares for handling redirects. Headless mode requires redirects to be handled by frontend app.

```
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.redirectMiddlewares'] = true;
```
To enable headless support for `EXT:redirect` please also add to you site(s) configuration's yaml file following flag:

`headless: true`


**headless.nextMajor**

Enable new APIs/behaviors of ext:headless, but contains breaking changes & require upgrade path for you application. Use with caution.
```
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.nextMajor'] = true;
```

**headless.jsonViewModule**

Enable new [BETA] backend module for previewing page (page type, language, usergroup and custom arguments). It is also possible to define new pageType views (ex. detail news preview if page has target plugin).
```
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.jsonViewModule'] = true;
```


## Development
Development for this extension is happening as part of the TYPO3 PWA initiative, see https://typo3.org/community/teams/typo3-development/initiatives/pwa/
If you have any questions, join #initiative-pwa Slack channel.

## Credits

A special thanks goes to [macopedia.com](https://macopedia.com) company, which is sponsoring development of this solution.

### Developers involved in the project

- Łukasz Uznański (Macopedia)
- Adam Marcinkowski (Macopedia)
- Vaclav Janoch (ITplusX)


