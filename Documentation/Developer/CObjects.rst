.. _developer-cobjects:

===================
TypoScript cObjects
===================

EXT:headless registers these cObjects:

* `JSON`
* `CONTENT_JSON`
* `BOOL`, `FLOAT`, `INT`

`BOOL`, `FLOAT` and `INT` take `value` and `value.` (stdWrap applied
to the value); every remaining top-level property is treated as stdWrap
configuration directly — `noIndex.field = no_index` works, there is no
`stdWrap` sub-key like core `TEXT`. They return a real bool / float /
int and only work as fields *inside* a `JSON` cObject, not in generic
TypoScript.

JSON
====

Builds a JSON object inline. The example adds a `share` object to the page
response, built from the `EXT:seo` page fields:

.. code-block:: typoscript

   page.10.fields.share = JSON
   page.10.fields.share {
     if.isFalse.field = no_index
     fields {
       title = TEXT
       title.field = og_title // seo_title // title
       description = TEXT
       description.field = og_description // description
       robots {
         fields {
           noFollow = BOOL
           noFollow.field = no_follow
         }
       }
       image = TEXT
       image {
         dataProcessing {
           10 = headless-files
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
   }

The JSON cObject understands these properties:

**if**

Render the object only when the condition is met.

**fields**

Array of child cObjects. Each field accepts:

* `intval` / `floatval` / `boolval` — cast the result.
* `ifEmptyReturnNull` — return `null` when the result is empty.
* `ifEmptyUnsetKey` — drop the key when the result is empty.
* `source` — nested field blocks only: output the block's `fields`
  result under a different key. Ignored on plain fields. When the block
  also defines `dataProcessing`, the output has two keys: the `fields`
  result under `source`, the processor result (merged with the `fields`
  result when `dataProcessingMerge = 1`) under the block's own key.
* `dataProcessing` — run data processors (see `image` above). A field
  whose cObject is `JSON` runs its processors itself, exactly once; its own
  `dataProcessingMerge`, `stdWrap` and `if` apply to the result.

Fields rendered by `USER_INT` stay uncached inside the cached JSON, see
:ref:`caching`.

**nullableFieldsIfEmpty**

Comma list of field names to null out when empty (bulk variant of
`ifEmptyReturnNull`).

**dataProcessing**

*Replaces* the `fields` output: the processors
run and the value registered under the last `as` key of the object's own
processors becomes the object's content (e.g.
:ref:`MenuProcessor <dataprocessors-menuprocessor>`). An `as` on a
field's processor or on a nested processor is never used for this.
When no processor declares `as`, or none of the declared names was
registered, the last variable the processors registered is used, so a
processor's default name such as `media` or `menu` works without `as`.
Set `dataProcessingMerge` to keep both.

**dataProcessingMerge**

Merge instead of replace. With
`dataProcessingMerge = 1` and `fields` present, the `fields` output is
kept and every processor result is added to it under the processor's
target (`as`) name; on a key collision the processor result wins.

.. code-block:: typoscript

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

   # {"title":"…","mainMenu":[…]} instead of the menu replacing the title

The flag also works on a nested field block that defines both `fields`
and `dataProcessing`, and on a field rendered as `JSON`. Without the
flag — or without `fields` — the behaviour is unchanged: the processors
replace the whole object.

**stdWrap**

`stdWrap` applied to the already-encoded JSON string.

CONTENT_JSON
============

Like core's `CONTENT`, but content elements are grouped by `colPos`
and JSON-encoded by default. All `CONTENT` options apply, plus four
JSON-specific extras:

**merge**

Run a second `CONTENT_JSON` query and merge the result into the
first — used for the `slide` feature. The grouped results are combined
with `array_merge` on their `colPosN` keys: when both queries return
elements for the same `colPos`, only the `merge` query's elements are
kept for it.

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

An empty result is encoded as `{}` with grouping enabled and as `[]`
with `doNotGroupByColPos = 1` — consumers must handle both types.

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
backend layout instead of numerically. Groups whose `colPos` is not in
the layout are dropped; layout columns without elements appear as
`[]`.

**returnSingleRow**

Return the first matched element as a single object instead of an
array — for one-record queries. Only takes effect together with
`doNotGroupByColPos = 1`; with the default `colPos` grouping the flag
is silently ignored.

.. code-block:: typoscript

   lib.header = CONTENT_JSON
   lib.header {
     table = tt_content
     select {
       orderBy = sorting
       max = 1
     }
     doNotGroupByColPos = 1
     returnSingleRow = 1
   }
