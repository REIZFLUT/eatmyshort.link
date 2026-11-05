<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * "%env(first_host:APP_ADMIN_HOSTS)%" -> first entry of a comma separated host list.
 */
final class FirstHostEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $hosts = array_values(array_filter(array_map('trim', explode(',', (string) $getEnv($name)))));

        return strtolower($hosts[0] ?? 'localhost');
    }

    public static function getProvidedTypes(): array
    {
        return ['first_host' => 'string'];
    }
}
