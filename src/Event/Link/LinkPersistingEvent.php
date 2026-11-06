<?php

declare(strict_types=1);

namespace App\Event\Link;

use App\Entity\Link;
use App\Event\RejectableEvent;

/**
 * A link is about to be written (created or updated).
 *
 * Rejecting aborts the write and shows the message to the caller: a form error in the
 * web UI, a 422 with {"error": ...} in the API. The link object is intentionally not
 * writable here - change data in the form/service that owns it, not from an event.
 *
 * @api
 *
 * @since 1.0
 */
final class LinkPersistingEvent implements RejectableEvent
{
    private ?string $rejectionMessage = null;

    public function __construct(
        private readonly Link $link,
        private readonly bool $isNew,
    ) {
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function reject(string $message): void
    {
        $this->rejectionMessage = $message;
    }

    public function isRejected(): bool
    {
        return null !== $this->rejectionMessage;
    }

    public function getRejectionMessage(): ?string
    {
        return $this->rejectionMessage;
    }
}
