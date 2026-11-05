<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Domain;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A user may create links on active domains assigned to them; admins on all active domains.
 *
 * @extends Voter<string, Domain>
 */
final class DomainVoter extends Voter
{
    public const string USE = 'DOMAIN_USE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::USE === $attribute && $subject instanceof Domain;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject->isActive()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        foreach ($user->getDomains() as $domain) {
            if ($domain->getId() === $subject->getId()) {
                return true;
            }
        }

        return false;
    }
}
