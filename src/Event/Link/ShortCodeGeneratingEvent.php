<?php

declare(strict_types=1);

namespace App\Event\Link;

use App\Entity\Domain;

/**
 * The app needs a new short code for a domain.
 *
 * A plugin that sets a code takes over generation for this call (readable slugs,
 * campaign prefixes, an external number range). The app still checks the code against
 * the database and falls back to its own random code when it is taken or invalid, so a
 * plugin cannot produce a duplicate or break the format rules.
 *
 * @api
 *
 * @since 1.0
 */
final class ShortCodeGeneratingEvent
{
    private ?string $shortCode = null;

    public function __construct(
        private readonly Domain $domain,
        private readonly int $length,
    ) {
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    /** Configured code length (Settings::CODE_LENGTH); a suggestion, not a hard rule. */
    public function getLength(): int
    {
        return $this->length;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }
}
