<?php

declare(strict_types=1);

namespace App\Plugin;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads config/plugins.yaml: the operator's whitelist that decides which plugins load.
 *
 * The file is a plain mapping of plugin name to {enabled, settings}. An optional
 * "when@<env>:" section uses the same shape and is merged over the base entries for the
 * running environment, so a single environment can enable, disable or reconfigure a
 * plugin without touching the shared list - the same convention the framework uses for
 * its own configuration.
 *
 * Only the names listed here are ever considered; a directory that is not named is
 * ignored. A missing file is not an error (that simply means "no plugins").
 *
 * @internal used by {@see PluginRegistry} and by the kernel while the container is built
 */
#[Exclude]
final class PluginConfig
{
    /** @var array<string, mixed>|null */
    private ?array $document = null;

    public function __construct(
        private readonly string $file,
    ) {
    }

    /**
     * Resolves the whitelist from APP_PLUGIN_CONFIG at runtime, so no environment-specific
     * path is baked into the compiled container.
     */
    public static function forProject(string $projectDir): self
    {
        $configured = $_SERVER['APP_PLUGIN_CONFIG'] ?? $_ENV['APP_PLUGIN_CONFIG'] ?? null;

        return new self(self::resolve($projectDir, \is_string($configured) ? $configured : null, 'config/plugins.yaml'));
    }

    /**
     * A configured path is used as given; a relative one is resolved against the project
     * directory, and an empty/missing setting falls back to the default.
     */
    public static function resolve(string $projectDir, ?string $configured, string $default): string
    {
        $path = null !== $configured && '' !== $configured ? $configured : $default;

        return str_starts_with($path, '/') ? $path : $projectDir.'/'.$path;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function exists(): bool
    {
        return is_file($this->file);
    }

    /**
     * Whitelist entries for an environment, base and when@<env> already merged.
     *
     * @return array<string, array{enabled: bool, settings: array<string, mixed>}>
     *
     * @throws PluginException when the file cannot be parsed or does not have the expected shape
     */
    public function entries(string $environment): array
    {
        $document = $this->document();

        $base = $this->rawEntries($document['plugins'] ?? null, 'plugins');
        $envSection = $document['when@'.$environment] ?? null;
        if (null !== $envSection) {
            if (!\is_array($envSection)) {
                throw new PluginException(\sprintf('Plugin whitelist "%s": "when@%s" must be a mapping.', $this->file, $environment));
            }
            $override = $this->rawEntries($envSection['plugins'] ?? null, 'when@'.$environment.'.plugins');
            foreach ($override as $name => $entry) {
                $base[$name] = isset($base[$name]) ? $this->mergeEntry($base[$name], $entry) : $entry;
            }
        }

        $entries = [];
        foreach ($base as $name => $entry) {
            $entries[$name] = [
                'enabled' => $entry['enabled'] ?? true,
                'settings' => $entry['settings'] ?? [],
            ];
        }
        ksort($entries);

        return $entries;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PluginException
     */
    private function document(): array
    {
        if (null !== $this->document) {
            return $this->document;
        }

        if (!is_file($this->file)) {
            return $this->document = [];
        }

        try {
            $parsed = Yaml::parseFile($this->file);
        } catch (ParseException $exception) {
            throw new PluginException(\sprintf('Plugin whitelist "%s" is not valid YAML: %s', $this->file, $exception->getMessage()), 0, $exception);
        }

        if (null === $parsed) {
            return $this->document = [];
        }
        if (!\is_array($parsed)) {
            throw new PluginException(\sprintf('Plugin whitelist "%s" must contain a mapping at the top level.', $this->file));
        }

        return $this->document = $parsed;
    }

    /**
     * Normalises one "plugins:" block, keeping track of whether "enabled" was set so the
     * when@<env> merge does not accidentally overwrite a value it never mentioned.
     *
     * @return array<string, array{enabled?: bool, settings?: array<string, mixed>}>
     *
     * @throws PluginException
     */
    private function rawEntries(mixed $raw, string $where): array
    {
        if (null === $raw) {
            return [];
        }
        if (!\is_array($raw)) {
            throw new PluginException(\sprintf('Plugin whitelist "%s": "%s" must be a mapping of plugin names.', $this->file, $where));
        }

        $entries = [];
        foreach ($raw as $name => $entry) {
            if (!\is_string($name) || !PluginRegistry::isValidName($name)) {
                throw new PluginException(\sprintf('Plugin whitelist "%s": invalid plugin name "%s" in "%s" (expected [a-z0-9-], 3-40 characters).', $this->file, \is_string($name) ? $name : gettype($name), $where));
            }
            if (null === $entry) {
                $entry = [];
            }
            if (!\is_array($entry)) {
                throw new PluginException(\sprintf('Plugin whitelist "%s": entry for "%s" must be a mapping.', $this->file, $name));
            }
            if (\array_key_exists('enabled', $entry) && !\is_bool($entry['enabled'])) {
                throw new PluginException(\sprintf('Plugin whitelist "%s": "enabled" for "%s" must be a boolean.', $this->file, $name));
            }
            if (\array_key_exists('settings', $entry) && !\is_array($entry['settings'])) {
                throw new PluginException(\sprintf('Plugin whitelist "%s": "settings" for "%s" must be a mapping.', $this->file, $name));
            }

            $normalised = [];
            if (\array_key_exists('enabled', $entry)) {
                $normalised['enabled'] = $entry['enabled'];
            }
            if (\array_key_exists('settings', $entry)) {
                /** @var array<string, mixed> $settings */
                $settings = $entry['settings'];
                $normalised['settings'] = $settings;
            }
            $entries[$name] = $normalised;
        }

        return $entries;
    }

    /**
     * @param array{enabled?: bool, settings?: array<string, mixed>} $base
     * @param array{enabled?: bool, settings?: array<string, mixed>} $override
     *
     * @return array{enabled?: bool, settings?: array<string, mixed>}
     */
    private function mergeEntry(array $base, array $override): array
    {
        $merged = $base;
        if (\array_key_exists('enabled', $override)) {
            $merged['enabled'] = $override['enabled'];
        }
        if (\array_key_exists('settings', $override)) {
            $merged['settings'] = array_replace_recursive($base['settings'] ?? [], $override['settings']);
        }

        return $merged;
    }
}
