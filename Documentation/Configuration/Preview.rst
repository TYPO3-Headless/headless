.. include:: ../Includes.txt

.. _preview:

===================
Preview hidden pages
===================

How to configure your application to preview hidden pages?
----------

Preview functionality is supported by headless extension, however you need to make sure that your application has expected configuration.

As there is no concept of cross domain cookies you need to make sure that both of your servers share root domain (backend: api.domain.com, frontend: domain.com). Afterwards you set the root domain as cookieDomain (note dot at the beginning)


.. code-block:: php

    $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'] = '.domain.com';

IMPORTANT If you are logged in to backend during this change (after deployment) you may need to remove be_typo_user cookie from your browser as it will collide with the new cookie and you won't be able to log in.

If your frontend application passes all cookies from backend correctly you should be able to preview content associated with the root domain.

Please note that if you have multi domain setup ex. api.domain1.com, domain1.com and api.domain2.com, domain2.com this solution won't work.
You will need to override TYPO3_CONF_VARS somewhere on fly. This hasn't been tested but overwriting it on custom middleware which would run as a first one could theoretically work.

(optional) Make backend application is available by proxy ex. domain.com/headless (it may not be necessary to run application however it solves a lot of issues down the road)
