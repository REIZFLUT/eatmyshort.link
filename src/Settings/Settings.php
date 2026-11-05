<?php

declare(strict_types=1);

namespace App\Settings;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Runtime settings editable in the admin area. Values are cached in cache.app and
 * invalidated on every write; unknown keys fall back to the defaults below.
 */
final class Settings implements ResetInterface
{
    public const string TITLE = 'app.title';
    public const string ACCENT = 'branding.accent';
    public const string LOGO = 'branding.logo';
    public const string REQUIRE_2FA_ADMINS = 'security.require_2fa_admins';
    public const string CODE_LENGTH = 'links.code_length';
    public const string DEFAULT_REDIRECT = 'links.default_redirect_type';
    public const string TRACKING = 'tracking.enabled';
    public const string TRACK_COUNTRY = 'tracking.country';

    public const array DEFAULTS = [
        self::TITLE => 'EatMyShort.Link',
        self::ACCENT => '#660099',
        self::LOGO => null,
        self::REQUIRE_2FA_ADMINS => '0',
        self::CODE_LENGTH => '6',
        self::DEFAULT_REDIRECT => '302',
        self::TRACKING => '1',
        self::TRACK_COUNTRY => '1',
    ];

    private const string CACHE_KEY = 'app_settings';

    /** @var array<string, ?string>|null */
    private ?array $values = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    public function get(string $key): ?string
    {
        $this->values ??= $this->cache->get(self::CACHE_KEY, fn (): array => $this->load());

        return \array_key_exists($key, $this->values) ? $this->values[$key] : (self::DEFAULTS[$key] ?? null);
    }

    public function getString(string $key): string
    {
        return (string) $this->get($key);
    }

    public function getInt(string $key): int
    {
        return (int) $this->get($key);
    }

    public function getBool(string $key): bool
    {
        return '1' === $this->get($key);
    }

    /** @param array<string, string|int|bool|null> $values */
    public function save(array $values): void
    {
        $repo = $this->em->getRepository(Setting::class);
        foreach ($values as $key => $value) {
            if (!\array_key_exists($key, self::DEFAULTS)) {
                throw new \InvalidArgumentException(\sprintf('Unknown setting "%s".', $key));
            }
            $value = \is_bool($value) ? ($value ? '1' : '0') : (null === $value ? null : (string) $value);
            $setting = $repo->find($key);
            if (null === $setting) {
                $this->em->persist(new Setting($key, $value));
            } else {
                $setting->setValue($value);
            }
        }
        $this->em->flush();
        $this->cache->delete(self::CACHE_KEY);
        $this->values = null;
    }

    public function reset(): void
    {
        $this->values = null;
    }

    /** @return array<string, ?string> */
    private function load(): array
    {
        $values = [];
        foreach ($this->em->getRepository(Setting::class)->findAll() as $setting) {
            $values[$setting->getKey()] = $setting->getValue();
        }

        return $values;
    }
}
