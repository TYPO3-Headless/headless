# TYPO3 Extension "headless"
Headless allows you to render JSON from TYPO3 content. You can customize output by changing types, names and nesting of fields.

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
As you can see, we use reserved keyword `fields` to render output. Keys, which are inside `fields` are used as keys in json, so if you specify key `abc = COA`, then in json output you will have
```
{
    "abc": "foo"
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

