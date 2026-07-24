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
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Redirects\Form\Element\ShortUrlElement as CoreShortUrlElement;

use function htmlspecialchars;
use function str_replace;

/**
 * Renders the short URL of a sys_redirect record with the site's frontendBase
 * as base URL when the matched site runs in full headless mode, so the
 * displayed/copied link points to the public frontend instead of the TYPO3
 * API host.
 */
class ShortUrlElement extends CoreShortUrlElement
{
    public function __construct(
        IconFactory $iconFactory,
        protected readonly SourceUrlResolver $urlResolver,
    ) {
        parent::__construct($iconFactory);
    }

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

        $normalizedParams = $request->getAttribute('normalizedParams');
        $scheme = $normalizedParams instanceof NormalizedParams && !$normalizedParams->isHttps() ? 'http' : 'https';
        $sourceUrl = $scheme . '://' . $sourceHost . $sourcePath;

        $resultArray['html'] = str_replace(
            htmlspecialchars($sourceUrl),
            htmlspecialchars($frontendUrl),
            $html
        );

        return $resultArray;
    }
}
