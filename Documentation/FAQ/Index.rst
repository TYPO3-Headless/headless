.. _faq:

===============
FAQ
===============

.. contents::
   :local:
   :depth: 3

How to use EXT:felogin?
----------

The usage of felogin with headless is not different from the common setup following the `felogin documentation <https://docs.typo3.org/c/typo3/cms-felogin/master/en-us/Index.html>`__.

To test the login without frontend, we can use browser extensions like `RESTer <https://addons.mozilla.org/de/firefox/addon/rester/>`__
(Firefox) or `Insomnia REST Client <https://chrome.google.com/webstore/detail/insomnia-rest-client/gmodihnfibbjdecbanmpmbmeffnmloel>`__
(Chrome) to simulate form submission. If the response contains a `set-cookie` header, the login was successful.

Does EXT:headless work with other extensions?
----------

Yes, basically all extension output can be rendered into the JSON response. Read the
:ref:`integration of external plugins <developer-plugin-external>` section of this documentation, or take a look into
the code of `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__ which can be seen as example how this works.
