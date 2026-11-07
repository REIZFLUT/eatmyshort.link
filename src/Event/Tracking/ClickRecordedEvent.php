<?php

declare(strict_types=1);

namespace App\Event\Tracking;

use App\Entity\Link;

/**
 * A click was stored. Read-only: the row is written, the counter is updated.
 *
 * Meant for pushing clicks onward (webhooks, M2M endpoints, log shipping). Runs inside
 * kernel.terminate after the visitor already got their redirect, so anything slow here
 * costs nobody a millisecond - but it also never reaches the response.
 *
 * @api
 *
 * @since 1.0
 */
final class ClickRecordedEvent
{
    /**
     * @param bool $uniqueVisitor true for this visitor's first click on this link on this calendar
     *                            day - the visitor the link statistics count. Best effort: two
     *                            simultaneous first clicks may both be reported as unique.
     */
    public function __construct(
        private readonly Link $link,
        private readonly \DateTimeImmutable $occurredAt,
        private readonly ?string $countryCode,
        private readonly ?string $deviceType,
        private readonly bool $uniqueVisitor,
    ) {
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** ISO 3166-1 alpha-2, e.g. "DE". Null without a GeoIP database. */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /** "desktop", "mobile", "tablet", "bot" or null. */
    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function isUniqueVisitor(): bool
    {
        return $this->uniqueVisitor;
    }
}
