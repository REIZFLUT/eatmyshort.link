<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResetToken>
 */
class ResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetToken::class);
    }

    public function findValid(string $publicToken): ?ResetToken
    {
        $parts = ResetToken::split($publicToken);
        if (null === $parts) {
            return null;
        }
        [$selector, $verifier] = $parts;
        $token = $this->findOneBy(['selector' => $selector]);

        return null !== $token && !$token->isExpired() && $token->matches($verifier) ? $token : null;
    }

    public function removeAllFor(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
