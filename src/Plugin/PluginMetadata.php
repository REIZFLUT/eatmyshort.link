<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * A plugin as declared by its plugin.yaml plus the settings from config/plugins.yaml.
 *
 * @api
 *
 * @since 1.0
 */
final class PluginMetadata
{
    /**
     * @param array<string, mixed> $settings  free-form, plugin-defined
     * @param string               $directory absolute path, no trailing slash
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly ?string $requiresApp,
        public readonly array $settings,
        public readonly string $directory,
    ) {
    }
}
