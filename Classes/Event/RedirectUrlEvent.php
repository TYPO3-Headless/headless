<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class RedirectUrlEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;
    private string $targetUrl;
    /**
     * @var array<string, mixed>
     */
    private array $redirectRecord;
    private int $targetStatusCode;
    private ServerRequestInterface $request;
    private UriInterface $originalTargetUrl;

    /**
     * @param array<string, mixed> $redirectRecord
     */
    public function __construct(
        ServerRequestInterface $request,
        UriInterface $originalTargetUrl,
        string $targetUrl,
        int $targetStatusCode,
        array $redirectRecord
    ) {
        $this->request = $request;
        $this->targetUrl = $targetUrl;
        $this->redirectRecord = $redirectRecord;
        $this->targetStatusCode = $targetStatusCode;
        $this->originalTargetUrl = $originalTargetUrl;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function getTargetStatusCode(): int
    {
        return $this->targetStatusCode;
    }

    public function setTargetStatusCode(int $targetStatusCode): void
    {
        $this->targetStatusCode = $targetStatusCode;
    }

    public function getOriginalTargetUrl(): UriInterface
    {
        return $this->originalTargetUrl;
    }

    public function getRedirectRecord(): array
    {
        return $this->redirectRecord;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
