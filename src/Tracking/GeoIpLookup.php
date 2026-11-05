<?php

declare(strict_types=1);

namespace App\Tracking;

use GeoIp2\Database\Reader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Optional country lookup in a local GeoLite2/DB-IP country database (.mmdb).
 * No external service is ever asked; without a database file it returns null.
 */
final class GeoIpLookup
{
    private ?Reader $reader = null;
    private bool $initialised = false;

    public function __construct(
        #[Autowire(env: 'default::GEOIP_DATABASE_PATH')]
        private readonly ?string $databasePath,
    ) {
    }

    public function isAvailable(): bool
    {
        return null !== $this->reader();
    }

    public function country(?string $ip): ?string
    {
        if (null === $ip || null === ($reader = $this->reader())) {
            return null;
        }
        try {
            $code = $reader->country($ip)->country->isoCode;
        } catch (\Throwable) {
            return null;
        }

        return null === $code ? null : strtoupper($code);
    }

    private function reader(): ?Reader
    {
        if (!$this->initialised) {
            $this->initialised = true;
            if (null !== $this->databasePath && '' !== $this->databasePath && is_readable($this->databasePath)) {
                $this->reader = new Reader($this->databasePath);
            }
        }

        return $this->reader;
    }
}
