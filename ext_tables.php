<?php
defined('TYPO3_MODE') || die();

call_user_func(
    function () {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'][] = \FriendsOfTYPO3\Headless\Hooks\PreviewUrlHook::class;
    }
);
