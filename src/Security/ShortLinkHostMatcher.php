<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\HostRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Matches requests to short-link domains (every host that is not an admin host).
 * Used for the "short_links" firewall without security: no session, no cookies.
 */
final readonly class ShortLinkHostMatcher implements RequestMatcherInterface
{
    public function __construct(private HostRegistry $hosts)
    {
    }

    public function matches(Request $request): bool
    {
        return !$this->hosts->isAdminHost($request->getHost());
    }
}
