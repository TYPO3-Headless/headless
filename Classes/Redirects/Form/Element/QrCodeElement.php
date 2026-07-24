<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Redirects\Form\Element;

use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Redirects\Form\Element\QrCodeElement as CoreQrCodeElement;

use function htmlspecialchars;
use function str_replace;

/**
 * Renders the QR code for a sys_redirect record with the site's frontendBase
 * as base URL when the matched site runs in full headless mode, so scanned
 * codes land on the public frontend instead of the TYPO3 API host.
 */
class QrCodeElement extends CoreQrCodeElement
{
    public function __construct(protected readonly SourceUrlResolver $urlResolver) {}

    public function render(): array
    {
        $resultArray = parent::render();

        $sourceHost = (string)($this->data['databaseRow']['source_host'] ?? '');
        $sourcePath = (string)($this->data['databaseRow']['source_path'] ?? '');
        $html = (string)($resultArray['html'] ?? '');
        $request = $this->data['request'] ?? null;

        if ($html === '' || $sourceHost === '' || $sourcePath === '' || !$request instanceof ServerRequestInterface) {
            return $resultArray;
        }

        $frontendUrl = $this->urlResolver->resolve($sourceHost, $sourcePath, $request);

        if ($frontendUrl === null) {
            return $resultArray;
        }

        $resultArray['html'] = str_replace(
            'content="https://' . $sourceHost . $sourcePath . '"',
            'content="' . htmlspecialchars($frontendUrl) . '"',
            $html
        );

        return $resultArray;
    }
}
