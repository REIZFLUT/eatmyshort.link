<?php

declare(strict_types=1);

namespace App;

use App\Security\AppSecretCheck;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Statistics group clicks by local calendar day, so the PHP timezone matters.
        $timezone = $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin';
        date_default_timezone_set(\is_string($timezone) && '' !== $timezone ? $timezone : 'Europe/Berlin');

        parent::boot();

        // Fail fast in production, including for console commands: TOTP seeds are only
        // recoverable with the real APP_SECRET, and a shipped placeholder would make
        // them decryptable for anybody who knows the template.
        if ('prod' === $this->getEnvironment()) {
            AppSecretCheck::assertUsable((string) $this->getContainer()->getParameter('kernel.secret'));
        }
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
