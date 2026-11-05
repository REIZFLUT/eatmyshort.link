<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminIpRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminIpRule>
 */
class AdminIpRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminIpRule::class);
    }

    /** @return list<string> */
    public function findAllCidrs(): array
    {
        return array_column(
            $this->createQueryBuilder('r')->select('r.cidr')->getQuery()->getArrayResult(),
            'cidr',
        );
    }

    /** @return list<AdminIpRule> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['createdAt' => 'ASC']);
    }
}
