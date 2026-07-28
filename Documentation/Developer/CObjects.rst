.. _developer-cobjects:

===================
TypoScript cObjects
===================

EXT:headless registers a handful of new cObjects:

* `JSON`
* `CONTENT_JSON`
* `BOOL`, `FLOAT`, `INT`

`BOOL`, `FLOAT` and `INT` take `value` and `stdWrap` properties like
`TEXT`, but return a real bool / float / int — they only work as
fields *inside* a `JSON` cObject, not in generic TypoScript.

JSON
====

Builds a JSON object inline.

.. code-block:: typoscript

  lib.meta = JSON
  lib.meta {
    if.isTrue = 1
    fields {
      title = TEXT
      title {
        field = seo_title
        stdWrap.ifEmpty.cObject = TEXT
        stdWrap.ifEmpty.cObject {
          field = title
        }
      }
      robots {
        fields {
          noIndex = BOOL
          noIndex.field = no_index
        }
      }
      ogImage = TEXT
      ogImage {
        dataProcessing {
          10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
          10 {
            as = media
            references.fieldName = og_image
            processingConfiguration {
              returnFlattenObject = 1
            }
          }
        }
      }
    }
    dataProcessing {
    }
    stdWrap {
    }
  }

The JSON cObject understands these properties:

**`if`** — render the object only when the condition is met.

**`fields`** — array of child cObjects. Each field accepts:

* `intval` / `floatval` / `boolval` — cast the result.
* `ifEmptyReturnNull` — return `null` when the result is empty.
* `ifEmptyUnsetKey` — drop the key when the result is empty.
* `source` — output the field under a different key.
* `dataProcessing` — run data processors (see `lib.meta.ogImage`).

**`nullableFieldsIfEmpty`** — comma list of field names to null out
when empty (bulk variant of `ifEmptyReturnNull`).

**`dataProcessing`** — *replaces* the `fields` output: the processors
run and the value registered under the last `as` key becomes the
object's content (e.g. :ref:`MenuProcessor <dataprocessors-menuprocessor>`).

**`stdWrap`** — `stdWrap` applied to the already-encoded JSON string.

CONTENT_JSON
============

Like core's `CONTENT`, but content elements are grouped by `colPos`
and JSON-encoded by default. All `CONTENT` options apply, plus four
JSON-specific extras:

**merge**

Run a second `CONTENT_JSON` query and merge the result into the
first — handy for the `slide` feature.

.. code-block:: typoscript

  lib.content = CONTENT_JSON
  lib.content {
    table = tt_content
    select {
      orderBy = sorting
      where = {#colPos} != 1
    }
    merge {
      table = tt_content
      select {
        orderBy = sorting
        where = {#colPos} = 1
      }
      slide = -1
    }
  }

**doNotGroupByColPos**

`0` (default) groups by `colPos` — every rendered element must then
expose a `colPos` field, otherwise rendering throws a
`RuntimeException`. `1` returns a flat JSON array.

.. code-block:: typoscript

  lib.content = CONTENT_JSON
  lib.content {
    table = tt_content
    select {
      orderBy = sorting
      where = {#colPos} != 1
    }
    doNotGroupByColPos = 1
  }

**sortByBackendLayout**

Order the `colPos` groups by the order of columns in the page's
backend layout instead of numerically.

**returnSingleRow**

Return the first matched element as a single object instead of an
array — for one-record queries.
