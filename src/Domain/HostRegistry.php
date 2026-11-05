<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Domain;
use App\Repository\DomainRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Knows which host serves the admin UI and which hosts are short-link domains.
 * Every host that is not an admin host is treated as a short-link domain.
 */
final class HostRegistry implements ResetInterface
{
    /** @var list<string> */
    private readonly array $adminHosts;

    /** @var array<string, ?Domain> */
    private array $resolved = [];

    public function __construct(
        private readonly DomainRepository $domains,
        #[Autowire(env: 'APP_ADMIN_HOSTS')]
        string $adminHosts,
    ) {
        $hosts = array_values(array_filter(array_map(
            static fn (string $host): string => Domain::normalizeHostname($host),
            explode(',', $adminHosts),
        )));
        if ([] === $hosts) {
            throw new \LogicException('APP_ADMIN_HOSTS must name at least one host.');
        }
        $this->adminHosts = $hosts;
    }

    public function isAdminHost(string $host): bool
    {
        return \in_array(Domain::normalizeHostname($host), $this->adminHosts, true);
    }

    /** Main admin host: WebAuthn relying party id and base of absolute links in mails. */
    public function getPrimaryAdminHost(): string
    {
        return $this->adminHosts[0];
    }

    /** @return list<string> */
    public function getAdminHosts(): array
    {
        return $this->adminHosts;
    }

    /** Registered short-link domain for the host, active or not. */
    public function resolveDomain(string $host): ?Domain
    {
        $host = Domain::normalizeHostname($host);

        if (!\array_key_exists($host, $this->resolved)) {
            $this->resolved[$host] = $this->domains->findOneByHostname($host);
        }

        return $this->resolved[$host];
    }

    public function isOwnHost(string $host): bool
    {
        return $this->isAdminHost($host) || null !== $this->resolveDomain($host);
    }

    public function reset(): void
    {
        $this->resolved = [];
    }
}
