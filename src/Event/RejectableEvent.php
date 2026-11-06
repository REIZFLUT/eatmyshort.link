<?php

declare(strict_types=1);

namespace App\Event;

/**
 * An event whose outcome the listener can refuse with a message the caller sees.
 *
 * Unlike a veto, a rejection is user-visible: it surfaces as a form error (web) or as
 * a 422 response (API). Use it to enforce policies, not to silently drop work.
 *
 * @api
 *
 * @since 1.0
 */
interface RejectableEvent
{
    /** @param string $message User-facing, translated by the plugin. */
    public function reject(string $message): void;

    public function isRejected(): bool;

    public function getRejectionMessage(): ?string;
}
