<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Domain;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Domain>
 */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    public function findOneByHostname(string $hostname): ?Domain
    {
        return $this->findOneBy(['hostname' => Domain::normalizeHostname($hostname)]);
    }

    /** @return list<Domain> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['hostname' => 'ASC']);
    }

    /**
     * Domains a user may create links on: admins get every active domain,
     * everybody else only the ones assigned to them.
     *
     * @return list<Domain>
     */
    public function findUsableBy(User $user): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.active = true')
            ->orderBy('d.isDefault', 'DESC')
            ->addOrderBy('d.hostname', 'ASC');

        if (!$user->isAdmin()) {
            $qb->innerJoin('d.users', 'u')->andWhere('u = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function clearDefaultExcept(Domain $domain): void
    {
        $this->createQueryBuilder('d')
            ->update()
            ->set('d.isDefault', 'false')
            ->where('d != :domain')
            ->setParameter('domain', $domain)
            ->getQuery()
            ->execute();
    }
}
