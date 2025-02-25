.. _preview:

===================
Preview Hidden Pages
===================

How to configure your application to preview hidden pages?
----------------------------------------------------------

The headless extension supports the preview functionality for hidden pages. However, proper configuration is required to ensure your application works as expected.

Since there is no concept of cross-domain cookies, ensure that both your backend and frontend servers share the same root domain (e.g., backend: api.domain.com, frontend: domain.com). Then, set the root domain as `cookieDomain` (note the dot at the beginning).

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'] = '.domain.com';

.. important::

   If you are logged into the backend during this change (after deployment), you may need to remove the `be_typo_user` cookie from your browser. The old cookie will conflict with the new configuration, preventing you from logging in.

If your frontend application correctly passes all cookies from the backend, you should be able to preview content associated with the root domain.

.. note::

   If you have a multi-domain setup, e.g., `api.domain1.com`, `domain1.com` and `api.domain2.com`, `domain2.com`, this solution will not work out-of-the-box. You will need to override `TYPO3_CONF_VARS` dynamically. This hasn't been extensively tested, but theoretically, you could achieve this by running custom middleware as the first one in the stack.

(Optional) Make the backend application available via a proxy, e.g., `domain.com/headless`. This step is not strictly necessary for the application to run but can help resolve various issues in the long run.
