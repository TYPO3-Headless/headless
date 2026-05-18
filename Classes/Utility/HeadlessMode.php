<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

#[AsAlias(public: true)]
final class HeadlessMode implements HeadlessModeInterface
{
    public const NONE = 0;
    public const FULL = 1;
    public const MIXED = 2;

    private ?ServerRequestInterface $request = null;

    public function withRequest(ServerRequestInterface $request): self
    {
        $clone = clone $this;
        $clone->request = $request;
        return $clone;
    }

    public function isEnabled(): bool
    {
        return $this->request !== null && $this->checkRequest($this->request);
    }

    public function isEnabledFor(ServerRequestInterface $request): bool
    {
        return $this->checkRequest($request);
    }

    private function checkRequest(ServerRequestInterface $request): bool
    {
        $mode = ($request->getAttribute('headless') ?? new Headless())->getMode();

        if ($mode === self::NONE) {
            return false;
        }
        if ($mode === self::FULL) {
            return true;
        }
        return $mode === self::MIXED
            && ($request->getHeader('Accept')[0] ?? '') === 'application/json';
    }

    public function overrideBackendRequestBySite(SiteInterface $site, ?SiteLanguage $language = null): ServerRequestInterface
    {
        if ($this->request === null) {
            throw new LogicException(
                'HeadlessMode::overrideBackendRequestBySite() requires a request; call withRequest() first.',
                1747200000
            );
        }

        $mode = (int)($site->getConfiguration()['headless'] ?? self::NONE);

        if ($mode === self::MIXED) {
            // in BE context we override
            $mode = $site->getSettings()->get('headless.preview.overrideMode', self::NONE);
        }

        $request = clone $this->request;

        if ($language) {
            $request = $request->withAttribute('language', $language);
        }

        return $request->withAttribute('headless', new Headless($mode));
    }
}
