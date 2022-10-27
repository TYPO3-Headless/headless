<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Service\Parser;

/**
 * @codeCoverageIgnore
 */
interface JsonParserInterface
{
    public function parseJson($jsonArray): bool;
}
