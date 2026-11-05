<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin privileges count only from addresses the admin IP policy allows. Outside the
 * allowlist an admin is treated like a normal user everywhere (own links, assigned
 * domains), not just locked out of /admin. Console commands have no request and keep
 * full rights.
 */
final readonly class AdminAccess
{
    public function __construct(
        private AdminIpPolicy $policy,
        private RequestStack $requestStack,
    ) {
    }

    public function isAdmin(?User $user): bool
    {
        if (null === $user || !$user->isAdmin()) {
            return false;
        }
        $request = $this->requestStack->getMainRequest();

        return null === $request || $this->policy->allows($request->getClientIp());
    }
}
