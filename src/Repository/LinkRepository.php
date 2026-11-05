<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Domain;
use App\Entity\Link;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Link>
 */
class LinkRepository extends ServiceEntityRepository
{
    /** @var array<string, string> sort key => DQL expression */
    private const array SORT_FIELDS = [
        'created' => 'l.createdAt',
        'clicks' => 'l.clickCount',
        'last_click' => 'l.lastClickAt',
        'target' => 'LOWER(l.targetUrl)',
        'code' => 'LOWER(l.code)',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Link::class);
    }

    /**
     * Hot path of every redirect: one query that fetches the link together with its
     * domain and localisations, so the controller needs no separate domain lookup.
     */
    public function findForRedirectByHost(string $host, string $code): ?Link
    {
        return $this->createQueryBuilder('l')
            ->addSelect('d', 'loc')
            ->innerJoin('l.domain', 'd')
            ->leftJoin('l.localisations', 'loc')
            ->where('d.hostname = :host')
            ->andWhere('l.code = :code')
            ->setParameter('host', Domain::normalizeHostname($host))
            ->setParameter('code', mb_strtolower($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function codeExists(Domain $domain, string $code): bool
    {
        return null !== $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.domain = :domain')
            ->andWhere('l.code = :code')
            ->setParameter('domain', $domain)
            ->setParameter('code', mb_strtolower($code))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Links of the user, or all links for admins ($onlyOwn = false), optionally filtered and sorted.
     *
     * The sort key is whitelisted here: unknown keys fall back to the default (created/desc),
     * so callers that pass no sort (API, dashboard) keep the previous behaviour. A stable id
     * tie-breaker keeps pagination deterministic when the primary sort key ties.
     *
     * @param string $sort one of created, clicks, last_click, target, code
     * @param string $dir  asc or desc
     */
    public function searchQuery(User $user, ?string $search = null, ?Domain $domain = null, bool $onlyOwn = true, string $sort = 'created', string $dir = 'desc'): QueryBuilder
    {
        $field = self::SORT_FIELDS[$sort] ?? self::SORT_FIELDS['created'];
        $direction = 'asc' === mb_strtolower($dir) ? \SortDirection::Ascending : \SortDirection::Descending;

        $qb = $this->createQueryBuilder('l')
            ->addSelect('d', 'o')
            ->innerJoin('l.domain', 'd')
            ->innerJoin('l.owner', 'o')
            ->orderBy($field, $direction)
            ->addOrderBy('l.id', \SortDirection::Descending);

        if ($onlyOwn) {
            $qb->andWhere('l.owner = :owner')->setParameter('owner', $user);
        }
        if (null !== $domain) {
            $qb->andWhere('l.domain = :domain')->setParameter('domain', $domain);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(l.code) LIKE :q OR LOWER(l.targetUrl) LIKE :q OR LOWER(l.title) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($search)).'%');
        }

        return $qb;
    }

    /** @return list<Link> */
    public function findRecentFor(User $user, int $limit = 5): array
    {
        return $this->searchQuery($user)->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countFor(?User $owner = null): int
    {
        $qb = $this->createQueryBuilder('l')->select('COUNT(l.id)');
        if (null !== $owner) {
            $qb->where('l.owner = :owner')->setParameter('owner', $owner);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countByDomain(Domain $domain): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.domain = :domain')
            ->setParameter('domain', $domain)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function transferOwnership(User $from, User $to): int
    {
        return $this->createQueryBuilder('l')
            ->update()
            ->set('l.owner', ':to')
            ->where('l.owner = :from')
            ->setParameter('to', $to)
            ->setParameter('from', $from)
            ->getQuery()
            ->execute();
    }
}
