<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Decrypts the TOTP seed when a user is loaded, so the 2FA provider can use it.
 */
#[AsEntityListener(event: Events::postLoad, entity: User::class)]
final readonly class TotpSecretListener
{
    public function __construct(private SecretBox $secretBox)
    {
    }

    public function postLoad(User $user): void
    {
        $encrypted = $user->getTotpSecretEncrypted();
        if (null !== $encrypted) {
            $user->setTotpSecret($this->secretBox->decrypt($encrypted), $encrypted);
        }
    }
}
