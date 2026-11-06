<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Doctrine's EntityUserProvider (security.yaml: provider app_users, `property: email`)
     * resolves the login identifier through `findOneBy(['email' => ...])`, passing the raw
     * value from the login form. E-mail addresses are stored trimmed and lowercased (see
     * User::setEmail()), so the identifier has to be normalised the same way before the
     * comparison. Without this the result depends on the driver's string collation: `=`
     * is exact on SQLite but case-insensitive by default on MariaDB/MySQL, so the same
     * input would log in on one driver and not on the other. Normalising here makes both
     * behave identically; there is no fuzzy or fallback matching.
     *
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return User|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        if (isset($criteria['email']) && \is_string($criteria['email'])) {
            $criteria['email'] = mb_strtolower(trim($criteria['email']));
        }

        return parent::findOneBy($criteria, $orderBy);
    }

    public function findOneByEmail(string $email): ?User
    {
        // Normalisation lives in findOneBy() so the login provider and this method share it.
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByWebauthnHandle(string $handle): ?User
    {
        return $this->findOneBy(['webauthnHandle' => $handle]);
    }

    public function countAdmins(): int
    {
        // roles is JSON; LIKE keeps this portable across SQLite/MariaDB/PostgreSQL.
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->andWhere('u.active = true')
            ->setParameter('role', '%"'.User::ROLE_ADMIN.'"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<User> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.displayName', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
