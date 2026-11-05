<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Stats\Period;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    public function testSeriesFillsGaps(): void
    {
        $period = Period::lastDays(7, new \DateTimeImmutable('2026-11-05 15:00'));
        $series = $period->series(['2026-11-03' => ['clicks' => 4, 'visitors' => 3]]);

        self::assertSame('2026-10-30', $period->from->format('Y-m-d'));
        self::assertCount(7, $series);
        self::assertSame(0, $series[0]['clicks']);
        self::assertSame(4, $series[4]['clicks']);
        self::assertSame(3, $series[4]['visitors']);
        self::assertSame('2026-11-05', $series[6]['day']->format('Y-m-d'));
    }

    public function testUnknownLengthFallsBackTo30Days(): void
    {
        self::assertSame(30, Period::lastDays(12)->days);
    }
}
