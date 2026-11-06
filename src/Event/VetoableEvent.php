<?php

declare(strict_types=1);

namespace App\Event;

/**
 * An event whose outcome the listener can abort.
 *
 * Vetoing means: the app must not perform the action it was about to perform, and the
 * visitor or caller must not be able to tell a plugin decided so (a blocked redirect
 * looks like an unknown link, a vetoed click is simply not recorded). Log the reason
 * instead of showing it.
 *
 * @api
 *
 * @since 1.0
 */
interface VetoableEvent
{
    /** @param string $reason Free-form, logged at info level. Never shown to visitors. */
    public function veto(string $reason = ''): void;

    public function isVetoed(): bool;

    public function getVetoReason(): ?string;
}
