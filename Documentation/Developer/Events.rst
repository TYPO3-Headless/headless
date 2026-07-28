.. _developer-events:

==========================
Events you can listen to
==========================

`EXT:headless` dispatches PSR-14 events where adjusting the JSON
output is most useful. For the full signature of each event class
see :ref:`ref-events`.

* `FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent` — fired in
  `FileUtility::process()` after a file's default properties have
  been collected, **before** crop-variants and autogenerate run.
  Use it to add custom fields (focus point, alt text from another
  source, signed CDN URL) to every file in the response. Two caveats:
  `process()` recurses for crop variants and autogenerate derivatives,
  so the event fires for each of them too; and a configured
  `properties.includeOnly` filter is applied *after* the event —
  list your custom keys there or they are dropped.

* `FriendsOfTYPO3\Headless\Event\FileDataAfterCropVariantProcessingEvent`
  — fired once per file after all crop variants are processed (also
  when there are none). Annotate or rewrite the complete file payload
  including `cropVariants`.

For customising the JSON redirect envelope, listen to the core
`TYPO3\CMS\Redirects\Event\RedirectWasHitEvent` — see
:ref:`integrations-redirects`.

Listener registration
=====================

.. code-block:: yaml

   # Configuration/Services.yaml
   services:
     Vendor\MyExt\EventListener\AddSignedCdnUrl:
       tags:
         - name: event.listener
           identifier: 'myext/file/signed-cdn-url'
           event: FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent

.. code-block:: php

   // Classes/EventListener/AddSignedCdnUrl.php
   final readonly class AddSignedCdnUrl
   {
       public function __invoke(EnrichFileDataEvent $event): void
       {
           $props = $event->getProperties();
           $props['signedUrl'] = $this->cdn->sign($props['publicUrl']);
           $event->setProperties($props);
       }
   }

After editing `Services.yaml`, run `bin/typo3 cache:flush` — the
container is compiled.
