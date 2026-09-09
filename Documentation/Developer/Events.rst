.. _developer-events:

==========================
Events you can listen to
==========================

The PSR-14 events `EXT:headless` dispatches, with their signatures and
caveats, are listed in :ref:`ref-events`. This page shows how to register
a listener.

Listener registration
=====================

The `#[AsEventListener]` attribute is the TYPO3 v14 way; no YAML is
needed. The example adds a signed CDN URL to every file in the response:

.. code-block:: php

   <?php

   declare(strict_types=1);

   namespace Vendor\MyExt\EventListener;

   use FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent;
   use TYPO3\CMS\Core\Attribute\AsEventListener;
   use Vendor\MyExt\Service\CdnSigner;

   #[AsEventListener(identifier: 'myext/file/signed-cdn-url')]
   final readonly class AddSignedCdnUrl
   {
       public function __construct(private CdnSigner $cdn) {}

       public function __invoke(EnrichFileDataEvent $event): void
       {
           $publicUrl = $event->getProcessed()->getPublicUrl();
           if ($publicUrl === null) {
               return;
           }

           $props = $event->getProperties();
           $props['signedUrl'] = $this->cdn->sign($publicUrl);
           $event->setProperties($props);
       }
   }

The file URL (`url`/`publicUrl`) is added to the payload only after the
event, so read it from the processed file. :php:`getPublicUrl()` returns
`null` when the file has no public URL.

Alternatively, register the listener via a `Services.yaml` tag:

.. code-block:: yaml

   # Configuration/Services.yaml
   services:
     Vendor\MyExt\EventListener\AddSignedCdnUrl:
       tags:
         - name: event.listener
           identifier: 'myext/file/signed-cdn-url'
           event: FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent

The container is compiled: run `bin/typo3 cache:flush` after adding or
changing a listener.
