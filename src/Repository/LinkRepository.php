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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Link::class);
    }

    /** Hot path of every redirect: one indexed lookup, localisations fetched along. */
    public function findForRedirect(Domain $domain, string $code): ?Link
    {
        return $this->createQueryBuilder('l')
            ->addSelect('loc')
            ->leftJoin('l.localisations', 'loc')
            ->where('l.domain = :domain')
            ->andWhere('l.code = :code')
            ->setParameter('domain', $domain)
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
     * Links visible to the user (own links; admins see all), optionally filtered.
     */
    public function searchQuery(User $user, ?string $search = null, ?Domain $domain = null, bool $onlyOwn = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->addSelect('d', 'o')
            ->innerJoin('l.domain', 'd')
            ->innerJoin('l.owner', 'o')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC');

        if ($onlyOwn || !$user->isAdmin()) {
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
        return $this->searchQuery($user, onlyOwn: true)->setMaxResults($limit)->getQuery()->getResult();
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

    /** Called by the click recorder within the click transaction. */
    public function incrementClicks(Link $link, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('l')
            ->update()
            ->set('l.clickCount', 'l.clickCount + 1')
            ->set('l.lastClickAt', ':at')
            ->where('l.id = :id')
            ->setParameter('at', $at, 'datetime_immutable')
            ->setParameter('id', $link->getId())
            ->getQuery()
            ->execute();
    }
}
