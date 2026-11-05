<?php

declare(strict_types=1);

namespace App\Stats;

use App\Entity\Link;
use App\Entity\User;
use App\Repository\ClickRepository;

/**
 * Collects everything a statistics page shows for one scope (link, owner or all).
 */
final readonly class StatsBuilder
{
    public function __construct(private ClickRepository $clicks)
    {
    }

    /**
     * @return array{period: Period, totals: array{clicks: int, visitors: int}, series: list<array{day: \DateTimeImmutable, clicks: int, visitors: int}>, breakdowns: array<string, list<array{value: ?string, clicks: int}>>}
     */
    public function build(Period $period, ?Link $link = null, ?User $owner = null): array
    {
        $breakdowns = [];
        foreach (ClickRepository::BREAKDOWN_FIELDS as $field) {
            $breakdowns[$field] = $this->clicks->breakdown($field, $period->from, $period->to, $link, $owner);
        }

        return [
            'period' => $period,
            'totals' => $this->clicks->totals($period->from, $period->to, $link, $owner),
            'series' => $period->series($this->clicks->perDay($period->from, $period->to, $link, $owner)),
            'breakdowns' => $breakdowns,
        ];
    }
}
