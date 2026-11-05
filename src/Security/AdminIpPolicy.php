<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\AdminIpRuleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Who may open /admin: the union of the rules in the database and the
 * ADMIN_IP_ALLOWLIST env var. An empty union means no restriction. Env entries
 * can't be removed from the UI, which makes them the lock-out safety net.
 */
final readonly class AdminIpPolicy
{
    /** @var list<string> */
    private array $envRules;

    public function __construct(
        private AdminIpRuleRepository $rules,
        #[Autowire(env: 'default::ADMIN_IP_ALLOWLIST')]
        ?string $envAllowlist,
    ) {
        $this->envRules = array_values(array_filter(array_map('trim', explode(',', (string) $envAllowlist))));
    }

    /** @return list<string> */
    public function getEnvRules(): array
    {
        return $this->envRules;
    }

    /** @return list<string> */
    public function getEffectiveRules(): array
    {
        return array_values(array_unique([...$this->envRules, ...$this->rules->findAllCidrs()]));
    }

    public function isRestricted(): bool
    {
        return [] !== $this->getEffectiveRules();
    }

    public function allows(?string $ip): bool
    {
        return self::allowsWith($ip, $this->getEffectiveRules());
    }

    /** @param list<string> $rules */
    public static function allowsWith(?string $ip, array $rules): bool
    {
        if ([] === $rules) {
            return true;
        }

        return null !== $ip && IpUtils::checkIp($ip, $rules);
    }

    /**
     * Would the given database rules still let this IP in? Used before saving
     * changes so admins can't lock themselves out by accident.
     *
     * @param list<string> $databaseRules
     */
    public function wouldAllow(?string $ip, array $databaseRules): bool
    {
        return self::allowsWith($ip, array_values(array_unique([...$this->envRules, ...$databaseRules])));
    }
}
