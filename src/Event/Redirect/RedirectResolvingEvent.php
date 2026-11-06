<?php

declare(strict_types=1);

namespace App\Event\Redirect;

use App\Entity\Link;
use App\Event\VetoableEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * A short link was resolved and is about to be sent as a redirect.
 *
 * Plugins may change the target (campaign parameters, A/B routing, region rules), pick
 * another redirect status, or veto the redirect entirely.
 *
 * @api
 *
 * @since 1.0
 */
final class RedirectResolvingEvent implements VetoableEvent
{
    private ?string $vetoReason = null;

    /**
     * @param int $statusCode One of 301, 302, 307, 308. Temporary codes are not cached
     *                        and are what the click counter relies on.
     */
    public function __construct(
        private readonly Request $request,
        private readonly Link $link,
        private string $target,
        private int $statusCode,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function veto(string $reason = ''): void
    {
        $this->vetoReason = '' === $reason ? 'vetoed by plugin' : $reason;
    }

    public function isVetoed(): bool
    {
        return null !== $this->vetoReason;
    }

    public function getVetoReason(): ?string
    {
        return $this->vetoReason;
    }
}
