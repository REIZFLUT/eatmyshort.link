<?php

declare(strict_types=1);

namespace App\Stats;

use App\Entity\Link;
use App\Entity\User;
use App\Repository\ClickRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Collects everything a statistics page shows for one scope (link, owner or all).
 *
 * In production the result is cached for 60 seconds: the aggregates scan every click of
 * the period and a dashboard load otherwise scales linearly with the click count. The
 * trade-off is that the numbers can be up to 60 seconds old; test and dev always read
 * fresh values so tests never see a previous test's counts.
 */
final readonly class StatsBuilder
{
    private const int CACHE_TTL = 60;

    public function __construct(
        private ClickRepository $clicks,
        private CacheInterface $cache,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /**
     * @return array{period: Period, totals: array{clicks: int, visitors: int}, series: list<array{day: \DateTimeImmutable, clicks: int, visitors: int}>, breakdowns: array<string, list<array{value: ?string, clicks: int}>>}
     */
    public function build(Period $period, ?Link $link = null, ?User $owner = null): array
    {
        if ('prod' !== $this->environment) {
            return $this->collect($period, $link, $owner);
        }

        $scope = match (true) {
            null !== $link => 'link-'.$link->getId(),
            null !== $owner => 'owner-'.$owner->getId(),
            default => 'global',
        };
        $key = \sprintf('stats.%s.%s.%s.%d', $scope, $period->from->format('Y-m-d'), $period->to->format('Y-m-d'), $period->days);

        /** @var array{period: Period, totals: array{clicks: int, visitors: int}, series: list<array{day: \DateTimeImmutable, clicks: int, visitors: int}>, breakdowns: array<string, list<array{value: ?string, clicks: int}>>} $result */
        $result = $this->cache->get($key, function (ItemInterface $item) use ($period, $link, $owner): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->collect($period, $link, $owner);
        });

        return $result;
    }

    /**
     * @return array{period: Period, totals: array{clicks: int, visitors: int}, series: list<array{day: \DateTimeImmutable, clicks: int, visitors: int}>, breakdowns: array<string, list<array{value: ?string, clicks: int}>>}
     */
    private function collect(Period $period, ?Link $link, ?User $owner): array
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
