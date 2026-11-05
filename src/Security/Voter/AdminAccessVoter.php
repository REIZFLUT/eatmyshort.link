<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\AdminAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Second line of defence behind AdminIpGuardListener: admin controllers are
 * marked with #[IsGranted('ADMIN_ACCESS')] and only a real admin from an
 * allowed address passes. AdminAccess already denies admins outside the
 * allowlist, so encoded paths or a missing route check cannot open /admin.
 *
 * @extends Voter<string, mixed>
 */
final class AdminAccessVoter extends Voter
{
    public const string ADMIN_ACCESS = 'ADMIN_ACCESS';

    public function __construct(private readonly AdminAccess $adminAccess)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ADMIN_ACCESS === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $this->adminAccess->isAdmin($user instanceof User ? $user : null);
    }
}
