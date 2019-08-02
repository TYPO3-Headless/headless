# TYPO3 Extension "headless" - JSON content API for TYPO3 PWA solution
Headless allows you to render JSON from TYPO3 content. You can customize output by changing types, names and nesting of fields.

This extension provides backend part (JSON API) for TYPO3 PWA solution. The other part is an extension providing JavaScript rendering for content based on Vue.js and Nuxt, which will be published soon on github. If you have any questions just drop a line in #initiative-pwa Slack channel.

## Features

- JSON API for content elements
- JSON API for navigation, layouts
- taking into account all language/translation configuration (e.g. fallback)
- support for EXTt:news
- easily extensible with custom fields or custom CE's

## Requirements
Extension requires TYPO3 in version at least 9.5.

## Installation
Install extension using composer\
``composer require friendsoftypo3/headless``

Then, you should include extension typoscript template, and you are ready to go.

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

## Credits

A special thanks goes to [macopedia.com](https://macopedia.com) company, which is sponsoring development of this solution.

### Developers involved in the project

- Łukasz Uznański (Macopedia)
- Adam Marcinkowski (Macopedia)
- Vaclav Janoch (ITplusX)
