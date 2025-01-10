<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Frontend;

use TYPO3\CMS\Backend\Routing\UriBuilder;

/**
 * @codeCoverageIgnore
 */
class BackendEditorUrl
{
    public function __construct(protected UriBuilder $uriBuilder) {}

    public function page(): string
    {
        return $this->generateUrl('pages');
    }

    public function record(): string
    {
        return $this->generateUrl();
    }

    private function generateUrl(string $table = 'tt_content'): string
    {
        $beUser = $GLOBALS['BE_USER'] ?? null;

        if ($beUser === null) {
            return '';
        }

        $params = [
            'edit' => [
                $table => [
                    '__id__' => 'edit',
                ],
            ],
        ];

        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', $params, UriBuilder::ABSOLUTE_URL);
    }
}
