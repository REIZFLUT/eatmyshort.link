<?php

declare(strict_types=1);

namespace App\Stats;

/**
 * A closed range of calendar days for the statistics pages ("7", "30", "90", "365" days).
 */
final readonly class Period
{
    public const array CHOICES = [7, 30, 90, 365];

    private function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public int $days,
    ) {
    }

    public static function lastDays(int $days, ?\DateTimeImmutable $today = null): self
    {
        $days = \in_array($days, self::CHOICES, true) ? $days : 30;
        $to = ($today ?? new \DateTimeImmutable())->setTime(0, 0);

        return new self($to->modify(\sprintf('-%d days', $days - 1)), $to, $days);
    }

    /**
     * Fills days without clicks with zeros.
     *
     * @param array<string, array{clicks: int, visitors: int}> $perDay
     *
     * @return list<array{day: \DateTimeImmutable, clicks: int, visitors: int}>
     */
    public function series(array $perDay): array
    {
        $series = [];
        for ($day = $this->from; $day <= $this->to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = ['day' => $day, 'clicks' => $perDay[$key]['clicks'] ?? 0, 'visitors' => $perDay[$key]['visitors'] ?? 0];
        }

        return $series;
    }
}
