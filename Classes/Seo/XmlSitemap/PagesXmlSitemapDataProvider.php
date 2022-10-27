<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo\XmlSitemap;

/**
 * @codeCoverageIgnore
 */
class PagesXmlSitemapDataProvider extends \TYPO3\CMS\Seo\XmlSitemap\PagesXmlSitemapDataProvider
{
    protected function defineUrl(array $data): array
    {
        $typoLinkConfig = [
            'parameter' => $data['uid'],
            'forceAbsoluteUrl' => 0,
        ];

        $data['loc'] = $this->cObj->typoLink_URL($typoLinkConfig);

        return $data;
    }
}
