Upgrade from 2.x to 3.x (BC release)
=======================

TYPO3 versions support
--
* `3.x` will support TYPO3 >= `11.5` **only**, if you are still on `10.4`/`9.5` please use `2.x` branch instead.

Feature flags
--
* `FrontendBaseUrlInPagePreview` flag will be removed. Please use `headless.frontendUrls` instead (available since 2.5 release)
* `headless.simplifiedLinkTarget` flag will be removed. Setting will on by default

API
--

__Stuff to be removed:__

* `FriendsOfTYPO3\Headless\Utility\ContentUtility` will be removed. Please use new `CONTENT_JSON` content object
* `FriendsOfTYPO3\Headless\Hooks\TypolinkHook` will be removed. Replaced with native TYPO3 core functionality
* `FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility` will be removed. Please use `FriendsOfTYPO3\Headless\Utility\UrlUtility`
* `FriendsOfTYPO3\Headless\Service\SiteService` will be removed. Please use `FriendsOfTYPO3\Headless\Utility\UrlUtility`
* `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['headless']['hooks']['redirectUrl']` hook will be removed. Please use `FriendsOfTYPO3\Headless\Event\RedirectUrlEvent`

__Changed behavior:__
* `FriendsOfTYPO3\Headless\Json\JsonEncoder` have dropped array input as requirement, so you can now encode objects etc, also by default encoder do not checks for possible json to decode, you have manually use `FriendsOfTYPO3\Headless\Json\JsonDecoder`

Typoscript
--
* By default, json output is streamlined & optimized. If you do not want to rewrite frontend app, please use `Configuration/TypoScript/2.x/setup.typoscript` instead default one.
