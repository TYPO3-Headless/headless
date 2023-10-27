<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Psr\Http\Message\ServerRequestInterface;

final class HeadlessMode
{
    public const NONE = 0;
    public const FULL = 1;
    public const MIXED = 2;

    private ?ServerRequestInterface $request = null;

    public function withRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }
    public function isEnabled(): bool
    {
        if ($this->request === null) {
            return false;
        }

        $headless = $this->request->getAttribute('headless') ?? new Headless();

        if ($headless->getMode() === self::NONE) {
            return false;
        }

        return $headless->getMode() === self::FULL ||
            ($headless->getMode() === self::MIXED && ($this->request->getHeader('Accept')[0] ?? '') === 'application/json');
    }
}
