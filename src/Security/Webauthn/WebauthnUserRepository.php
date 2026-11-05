<?php

declare(strict_types=1);

namespace App\Security\Webauthn;

use App\Entity\User;
use App\Repository\UserRepository;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Maps app users to WebAuthn user entities: name = e-mail, id = random handle.
 * Users are never created through WebAuthn (no open registration).
 */
final readonly class WebauthnUserRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        return self::toEntity($this->users->findOneByEmail($username));
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        return self::toEntity($this->users->findOneByWebauthnHandle($userHandle));
    }

    private static function toEntity(?User $user): ?PublicKeyCredentialUserEntity
    {
        if (null === $user || !$user->isActive()) {
            return null;
        }

        return PublicKeyCredentialUserEntity::create($user->getEmail(), $user->getWebauthnHandle(), $user->getDisplayName());
    }
}
