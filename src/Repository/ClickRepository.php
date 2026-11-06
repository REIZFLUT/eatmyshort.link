<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Click;
use App\Entity\Link;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Statistics queries. Bots are always excluded. The scope is either a single link,
 * all links of an owner, or everything (admin overview).
 *
 * @extends ServiceEntityRepository<Click>
 */
class ClickRepository extends ServiceEntityRepository
{
    public const array BREAKDOWN_FIELDS = ['referrerHost', 'countryCode', 'deviceType', 'browser', 'os', 'language'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Click::class);
    }

    /**
     * @return array<string, array{clicks: int, visitors: int}> keyed by Y-m-d, only days with clicks
     */
    public function perDay(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Link $link = null, ?User $owner = null): array
    {
        $rows = $this->scoped($from, $to, $link, $owner)
            ->select('c.clickedDay AS day', 'COUNT(c.id) AS clicks', 'COUNT(DISTINCT c.visitorHash) AS visitors')
            ->groupBy('c.clickedDay')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $day = $row['day'] instanceof \DateTimeInterface ? $row['day']->format('Y-m-d') : substr((string) $row['day'], 0, 10);
            $result[$day] = ['clicks' => (int) $row['clicks'], 'visitors' => (int) $row['visitors']];
        }

        return $result;
    }

    /** @return array{clicks: int, visitors: int} */
    public function totals(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Link $link = null, ?User $owner = null): array
    {
        $row = $this->scoped($from, $to, $link, $owner)
            ->select('COUNT(c.id) AS clicks', 'COUNT(DISTINCT c.visitorHash) AS visitors')
            ->getQuery()
            ->getSingleResult();

        return ['clicks' => (int) $row['clicks'], 'visitors' => (int) $row['visitors']];
    }

    /**
     * @return list<array{value: ?string, clicks: int}>
     */
    public function breakdown(string $field, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Link $link = null, ?User $owner = null, int $limit = 10): array
    {
        if (!\in_array($field, self::BREAKDOWN_FIELDS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown breakdown field "%s".', $field));
        }

        $rows = $this->scoped($from, $to, $link, $owner)
            ->select("c.$field AS value", 'COUNT(c.id) AS clicks')
            ->groupBy("c.$field")
            ->orderBy('clicks', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): array => [
            'value' => null === $row['value'] ? null : (string) $row['value'],
            'clicks' => (int) $row['clicks'],
        ], $rows));
    }

    /**
     * @return list<array{link: Link, clicks: int}>
     */
    public function topLinks(\DateTimeImmutable $from, \DateTimeImmutable $to, ?User $owner = null, int $limit = 10): array
    {
        $rows = $this->scoped($from, $to, null, $owner)
            ->select('IDENTITY(c.link) AS linkId', 'COUNT(c.id) AS clicks')
            ->groupBy('c.link')
            ->orderBy('clicks', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $links = [];
        $repo = $this->getEntityManager()->getRepository(Link::class);
        foreach ($rows as $row) {
            $linkEntity = $repo->find($row['linkId']);
            if (null !== $linkEntity) {
                $links[] = ['link' => $linkEntity, 'clicks' => (int) $row['clicks']];
            }
        }

        return $links;
    }

    /**
     * Streams clicks for the CSV export with a constant memory footprint: every hydrated
     * entity is detached again after the consumer has written it, so neither the identity
     * map nor the unit of work grow with the number of exported rows.
     *
     * @return \Generator<int, Click>
     */
    public function iterateForExport(Link $link, \DateTimeImmutable $from, \DateTimeImmutable $to): \Generator
    {
        $em = $this->getEntityManager();
        $query = $this->createQueryBuilder('c')
            ->where('c.link = :link')
            ->andWhere('c.clickedDay BETWEEN :from AND :to')
            ->setParameter('link', $link)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('c.clickedAt', \SortDirection::Ascending)
            ->getQuery();

        foreach ($query->toIterable() as $click) {
            try {
                yield $click;
            } finally {
                $em->detach($click);
            }
        }
    }

    /**
     * Deletes old clicks in small chunks. One big DELETE would hold SQLite's write lock
     * longer than the busy timeout (5 s) and block tracking for the whole duration.
     *
     * Each chunk is selected first and then deleted by primary key. The previous
     * single statement used SQLite's `rowid` in the subquery and would additionally be
     * rejected by MySQL, which refuses to modify a table that a subquery in the same
     * statement reads. Selecting the ids keeps the chunking intact: the write lock is
     * released between chunks and the redirect writer gets a window to run.
     */
    public function deleteOlderThan(\DateTimeImmutable $day, int $chunkSize = 5000): int
    {
        $connection = $this->getEntityManager()->getConnection();
        $deleted = 0;

        while (true) {
            /** @var list<int> $ids */
            $ids = array_map(intval(...), $connection->executeQuery(
                'SELECT id FROM click WHERE clicked_day < ? ORDER BY id LIMIT ?',
                [$day->format('Y-m-d'), $chunkSize],
                [ParameterType::STRING, ParameterType::INTEGER],
            )->fetchFirstColumn());

            // Nothing older left.
            if ([] === $ids) {
                break;
            }

            $affected = $connection->executeStatement(
                'DELETE FROM click WHERE id IN (?)',
                [$ids],
                [ArrayParameterType::INTEGER],
            );
            $deleted += $affected;

            // A chunk shorter than the limit means this was the last one.
            if (\count($ids) < $chunkSize) {
                break;
            }
            // Give the redirect writer a chance to get the lock between chunks.
            usleep(10000);
        }

        return $deleted;
    }

    private function scoped(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Link $link, ?User $owner): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.bot = false')
            ->andWhere('c.clickedDay BETWEEN :from AND :to')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE);

        if (null !== $link) {
            $qb->andWhere('c.link = :link')->setParameter('link', $link);
        } elseif (null !== $owner) {
            $qb->innerJoin('c.link', 'l')->andWhere('l.owner = :owner')->setParameter('owner', $owner);
        }

        return $qb;
    }
}
