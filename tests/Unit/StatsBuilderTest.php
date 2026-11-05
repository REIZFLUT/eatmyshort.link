<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Repository\ClickRepository;
use App\Stats\Period;
use App\Stats\StatsBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class StatsBuilderTest extends TestCase
{
    public function testProductionBuildIsCachedForSixtySeconds(): void
    {
        $totals = ['clicks' => 5, 'visitors' => 3];
        $calls = 0;
        $clicks = $this->createStub(ClickRepository::class);
        $clicks->method('breakdown')->willReturn([]);
        $clicks->method('perDay')->willReturn([]);
        $clicks->method('totals')->willReturnCallback(static function () use (&$calls, &$totals): array {
            ++$calls;

            return $totals;
        });

        $builder = new StatsBuilder($clicks, new ArrayAdapter(), 'prod');
        $period = Period::lastDays(7, new \DateTimeImmutable('2026-11-05 12:00'));

        $first = $builder->build($period);
        $totals = ['clicks' => 99, 'visitors' => 50];
        $second = $builder->build($period);

        self::assertSame(5, $first['totals']['clicks']);
        self::assertSame(5, $second['totals']['clicks'], 'the result inside the TTL comes from the cache');
        self::assertSame(1, $calls, 'the aggregates run only once per key and TTL');
    }

    public function testTestEnvironmentAlwaysReadsFreshNumbers(): void
    {
        $totals = ['clicks' => 5, 'visitors' => 3];
        $calls = 0;
        $clicks = $this->createStub(ClickRepository::class);
        $clicks->method('breakdown')->willReturn([]);
        $clicks->method('perDay')->willReturn([]);
        $clicks->method('totals')->willReturnCallback(static function () use (&$calls, &$totals): array {
            ++$calls;

            return $totals;
        });

        $builder = new StatsBuilder($clicks, new ArrayAdapter(), 'test');
        $period = Period::lastDays(7, new \DateTimeImmutable('2026-11-05 12:00'));

        self::assertSame(5, $builder->build($period)['totals']['clicks']);
        $totals = ['clicks' => 9, 'visitors' => 4];
        self::assertSame(9, $builder->build($period)['totals']['clicks']);
        self::assertSame(2, $calls);
    }
}
