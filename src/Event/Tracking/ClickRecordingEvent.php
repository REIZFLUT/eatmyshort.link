<?php

declare(strict_types=1);

namespace App\Event\Tracking;

use App\Entity\Link;
use App\Event\VetoableEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * A click is about to be stored.
 *
 * Dispatched after the response was sent (kernel.terminate runs after
 * fastcgi_finish_request()), so listeners here must be fast and must not depend on a
 * rendered response. Vetoing means the click is not written at all - use it to keep
 * bots, internal traffic or a customer's test calls out of the statistics.
 *
 * @api
 *
 * @since 1.0
 */
final class ClickRecordingEvent implements VetoableEvent
{
    private ?string $vetoReason = null;

    public function __construct(
        private readonly Request $request,
        private readonly Link $link,
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
