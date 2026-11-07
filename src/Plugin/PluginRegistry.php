<?php

declare(strict_types=1);

namespace App\Plugin;

use Composer\Semver\Semver;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns the whitelist from config/plugins.yaml and the directories under plugins/ into a
 * list of plugins the container can load.
 *
 * The contract is fail-closed: an enabled plugin whose directory is missing, whose
 * plugin.yaml cannot be parsed or is missing a required field, whose declared name does
 * not match its directory, or that asks for a plugin API this app does not provide makes
 * {@see self::active()} throw a {@see PluginException} - a broken plugin must stop the
 * boot, never half-load.
 *
 * A directory that is not named in the whitelist is not a plugin at all: it is skipped
 * entirely and only surfaces as a warning from {@see self::audit()}.
 *
 * @api
 *
 * @since 1.0
 */
final class PluginRegistry
{
    public function __construct(
        private readonly string $pluginDir,
        private readonly PluginConfig $config,
        private readonly string $apiVersion = PluginApi::VERSION,
    ) {
    }

    /**
     * Builds a registry for an application: the plugins directory (APP_PLUGIN_DIR, relative
     * paths against the project directory) and the whitelist (APP_PLUGIN_CONFIG). Reading
     * the environment here, instead of from a container parameter, keeps the compiled
     * container independent of where the plugins live.
     */
    public static function forProject(string $projectDir): self
    {
        $configured = $_SERVER['APP_PLUGIN_DIR'] ?? $_ENV['APP_PLUGIN_DIR'] ?? null;

        return new self(
            PluginConfig::resolve($projectDir, \is_string($configured) ? $configured : null, 'plugins'),
            PluginConfig::forProject($projectDir),
        );
    }

    public function pluginDir(): string
    {
        return $this->pluginDir;
    }

    public function config(): PluginConfig
    {
        return $this->config;
    }

    /**
     * Every enabled, valid plugin, in whitelist order.
     *
     * @return list<PluginMetadata>
     *
     * @throws PluginException when an enabled plugin is misconfigured
     */
    public function active(string $environment): array
    {
        $plugins = [];
        foreach ($this->config->entries($environment) as $name => $entry) {
            if (!$entry['enabled']) {
                continue;
            }
            $plugins[] = $this->load($name, $entry['settings']);
        }

        return $plugins;
    }

    /**
     * {@see self::active()}, memoised in $cacheFile for the kernel's per-request boot.
     *
     * Resolving the whitelist parses YAML and checks Semver constraints - about 0.5 ms with no
     * plugin enabled and more with every enabled one, paid on each request, redirects included.
     * The result is stored together with a signature of everything it was derived from: the
     * paths, the API version and a content hash of the whitelist and of every enabled
     * plugin's manifest. A cached result is only used while that signature still
     * matches, so editing, replacing or removing any of those files resolves afresh (and a
     * broken plugin still stops the boot). The cache is a best-effort optimisation: a file
     * that cannot be read or written is ignored.
     *
     * @return list<PluginMetadata>
     *
     * @throws PluginException when an enabled plugin is misconfigured
     */
    public function activeCached(string $environment, string $cacheFile): array
    {
        $cached = is_file($cacheFile) ? @file_get_contents($cacheFile) : false;
        if (\is_string($cached)) {
            $data = @unserialize($cached, ['allowed_classes' => false]);
            if (\is_array($data) && isset($data['plugins'], $data['manifests']) && \is_array($data['plugins']) && \is_array($data['manifests'])) {
                /** @var list<string> $manifests */
                $manifests = $data['manifests'];
                if (($data['signature'] ?? null) === $this->signature($environment, $manifests)) {
                    $plugins = [];
                    foreach ($data['plugins'] as $row) {
                        if (!\is_array($row)) {
                            $plugins = null;
                            break;
                        }
                        $plugins[] = new PluginMetadata(...$row);
                    }
                    if (null !== $plugins) {
                        return $plugins;
                    }
                }
            }
        }

        $plugins = $this->active($environment);
        $manifests = array_map(static fn (PluginMetadata $plugin): string => $plugin->directory.'/plugin.yaml', $plugins);

        $payload = serialize([
            'signature' => $this->signature($environment, $manifests),
            'manifests' => $manifests,
            'plugins' => array_map(static fn (PluginMetadata $plugin): array => [
                'name' => $plugin->name,
                'version' => $plugin->version,
                'description' => $plugin->description,
                'requiresApi' => $plugin->requiresApi,
                'settings' => $plugin->settings,
                'directory' => $plugin->directory,
            ], $plugins),
        ]);

        $directory = \dirname($cacheFile);
        if (is_dir($directory) || @mkdir($directory, 0o777, true) || is_dir($directory)) {
            // Write and rename, so a concurrent boot never reads a half-written file.
            $temporary = $cacheFile.'.'.bin2hex(random_bytes(4)).'.tmp';
            if (false !== @file_put_contents($temporary, $payload) && !@rename($temporary, $cacheFile)) {
                @unlink($temporary);
            }
        }

        return $plugins;
    }

    /**
     * @param list<string> $manifests
     *
     * @return array<string, mixed>
     */
    private function signature(string $environment, array $manifests): array
    {
        // A content hash, not mtime: two edits within the same second must not be mistaken
        // for one. The files are small, xxh128 over them costs a few microseconds.
        $stat = static function (string $file): ?string {
            $hash = is_file($file) ? @hash_file('xxh128', $file) : false;

            return false === $hash ? null : $hash;
        };

        $files = [];
        foreach ($manifests as $manifest) {
            $files[$manifest] = $stat($manifest);
        }

        return [
            'environment' => $environment,
            'api' => $this->apiVersion,
            'plugin_dir' => $this->pluginDir,
            'config' => [$this->config->file(), $stat($this->config->file())],
            'manifests' => $files,
        ];
    }

    /**
     * One row per whitelisted plugin for "app:plugin:list". Never throws: a plugin that is
     * enabled but broken is still shown, so the command stays usable for diagnosis.
     *
     * @return list<array{name: string, version: ?string, state: string, requires: ?string, directory: string}>
     */
    public function status(string $environment): array
    {
        $rows = [];
        foreach ($this->config->entries($environment) as $name => $entry) {
            $directory = $this->pluginDir.'/'.$name;
            $manifest = is_dir($directory) ? $this->readManifest($directory) : null;

            if (!$entry['enabled']) {
                $state = 'deaktiviert';
            } elseif (!is_dir($directory)) {
                $state = 'nicht gefunden';
            } else {
                $state = 'aktiv';
            }

            $requires = $manifest['requires'] ?? null;
            $rows[] = [
                'name' => $name,
                'version' => \is_string($manifest['version'] ?? null) ? $manifest['version'] : null,
                'state' => $state,
                'requires' => \is_array($requires) && \is_string($requires['api'] ?? null) ? $requires['api'] : null,
                'directory' => $directory,
            ];
        }

        return $rows;
    }

    /**
     * All present plugin directories that are not named in the whitelist.
     *
     * @return list<string>
     */
    public function unlisted(string $environment): array
    {
        if (!is_dir($this->pluginDir)) {
            return [];
        }

        $known = array_keys($this->config->entries($environment));
        $unlisted = [];
        foreach (scandir($this->pluginDir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry || \in_array($entry, $known, true)) {
                continue;
            }
            if (is_dir($this->pluginDir.'/'.$entry)) {
                $unlisted[] = $entry;
            }
        }
        sort($unlisted);

        return $unlisted;
    }

    /**
     * Everything "app:plugin:validate" should report. Errors stop the boot; warnings are
     * advisories (an unlisted directory, an unexpected namespace, world-writable files).
     *
     * @return array{errors: list<string>, warnings: list<string>}
     *
     * @throws PluginException when the whitelist itself cannot be read
     */
    public function audit(string $environment): array
    {
        $errors = [];
        $warnings = [];

        foreach ($this->config->entries($environment) as $name => $entry) {
            $directory = $this->pluginDir.'/'.$name;

            if (!is_dir($directory)) {
                if ($entry['enabled']) {
                    $errors[] = \sprintf('Plugin "%s" is enabled in the whitelist but its directory "%s" does not exist.', $name, $directory);
                }
                continue;
            }

            if ($entry['enabled']) {
                foreach ($this->inspectManifest($name, $directory)['problems'] as $problem) {
                    $errors[] = $problem;
                }
            }

            foreach ($this->permissionWarnings($name, $directory) as $warning) {
                $warnings[] = $warning;
            }
            foreach ($this->namespaceWarnings($name, $directory) as $warning) {
                $warnings[] = $warning;
            }
        }

        foreach ($this->unlisted($environment) as $name) {
            $warnings[] = \sprintf('Directory "%s" exists but is not listed in the whitelist; it is ignored (no autoload, no services, no routes).', $this->pluginDir.'/'.$name);
        }

        sort($errors);
        sort($warnings);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * The namespace a plugin's classes live in, derived from its directory name:
     * "example-utm" -> "Plugin\ExampleUtm".
     */
    public static function namespaceFor(string $name): string
    {
        return 'Plugin\\'.str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    /**
     * A plugin name is its directory name: [a-z0-9-], 3-40 characters.
     */
    public static function isValidName(string $name): bool
    {
        return 1 === preg_match('/^[a-z0-9-]{3,40}$/', $name);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @throws PluginException
     */
    private function load(string $name, array $settings): PluginMetadata
    {
        $directory = $this->pluginDir.'/'.$name;
        if (!is_dir($directory)) {
            throw new PluginException(\sprintf('Plugin "%s" is enabled in the whitelist but its directory "%s" does not exist.', $name, $directory));
        }

        $inspection = $this->inspectManifest($name, $directory);
        if ([] !== $inspection['problems']) {
            throw new PluginException($inspection['problems'][0]);
        }
        $data = $inspection['data'];

        $version = \is_string($data['version'] ?? null) ? $data['version'] : '';
        $description = \is_string($data['description'] ?? null) ? $data['description'] : '';
        $requires = $data['requires'] ?? null;
        $requiresApi = \is_array($requires) && \is_string($requires['api'] ?? null) ? $requires['api'] : null;

        return new PluginMetadata($name, $version, $description, $requiresApi, $settings, $directory);
    }

    /**
     * Reads and checks plugin.yaml without throwing: the caller decides whether a problem
     * aborts the boot ({@see self::load()}) or is merely reported ({@see self::audit()}).
     *
     * @return array{data: array<string, mixed>, problems: list<string>}
     */
    private function inspectManifest(string $name, string $directory): array
    {
        $manifest = $directory.'/plugin.yaml';
        if (!is_file($manifest)) {
            return ['data' => [], 'problems' => [\sprintf('Plugin "%s": manifest "%s" is missing.', $name, $manifest)]];
        }

        try {
            $parsed = Yaml::parseFile($manifest);
        } catch (ParseException $exception) {
            return ['data' => [], 'problems' => [\sprintf('Plugin "%s": cannot parse "%s": %s', $name, $manifest, $exception->getMessage())]];
        }
        if (!\is_array($parsed)) {
            return ['data' => [], 'problems' => [\sprintf('Plugin "%s": manifest "%s" must contain a mapping.', $name, $manifest)]];
        }

        /** @var array<string, mixed> $data */
        $data = $parsed;
        $problems = [];

        foreach (['name', 'version', 'description'] as $field) {
            $value = $data[$field] ?? null;
            if (!\is_string($value) || '' === trim($value)) {
                $problems[] = \sprintf('Plugin "%s": required field "%s" is missing or empty in "%s".', $name, $field, $manifest);
            }
        }

        $declared = $data['name'] ?? null;
        if (\is_string($declared) && $declared !== $name) {
            $problems[] = \sprintf('Plugin "%s": declared name "%s" does not match its directory "%s".', $name, $declared, $name);
        }

        if (\array_key_exists('requires', $data) && null !== $data['requires']) {
            $requires = $data['requires'];
            if (!\is_array($requires)) {
                $problems[] = \sprintf('Plugin "%s": "requires" must be a mapping.', $name);
            } elseif (\array_key_exists('api', $requires)) {
                $api = $requires['api'];
                if (!\is_string($api)) {
                    $problems[] = \sprintf('Plugin "%s": "requires.api" must be a string.', $name);
                } else {
                    try {
                        if (!Semver::satisfies($this->apiVersion, $api)) {
                            $problems[] = \sprintf('Plugin "%s" requires plugin API "%s", but this application provides "%s".', $name, $api, $this->apiVersion);
                        }
                    } catch (\UnexpectedValueException $exception) {
                        $problems[] = \sprintf('Plugin "%s": "requires.api" constraint "%s" is not valid (%s).', $name, $api, $exception->getMessage());
                    }
                }
            }
        }

        return ['data' => $data, 'problems' => $problems];
    }

    /**
     * Best-effort read of plugin.yaml for display; a plugin that cannot be read simply
     * shows up with an empty version in the list instead of making the command fail.
     *
     * @return array<string, mixed>|null
     */
    private function readManifest(string $directory): ?array
    {
        $manifest = $directory.'/plugin.yaml';
        if (!is_file($manifest)) {
            return null;
        }

        try {
            $parsed = Yaml::parseFile($manifest);
        } catch (ParseException) {
            return null;
        }

        return \is_array($parsed) ? $parsed : null;
    }

    /**
     * @return list<string>
     */
    private function permissionWarnings(string $name, string $directory): array
    {
        $warnings = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if (0 !== ($file->getPerms() & 0o002)) {
                $warnings[] = \sprintf('Plugin "%s": %s "%s" is world-writable; a plugin runs with the app\'s own rights, so it must not be writable by every user.', $name, $file->isDir() ? 'directory' : 'file', $file->getPathname());
            }
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function namespaceWarnings(string $name, string $directory): array
    {
        $src = $directory.'/src';
        if (!is_dir($src)) {
            return [];
        }

        $expected = self::namespaceFor($name);
        $warnings = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }
            $namespace = $this->extractNamespace($file->getPathname());
            if (null === $namespace || $namespace === $expected || str_starts_with($namespace, $expected.'\\')) {
                continue;
            }
            $warnings[] = \sprintf('Plugin "%s": class in "%s" declares namespace "%s", outside "%s\\".', $name, $file->getPathname(), $namespace, $expected);
        }

        return $warnings;
    }

    private function extractNamespace(string $file): ?string
    {
        $contents = file_get_contents($file);
        if (false === $contents) {
            return null;
        }

        $tokens = token_get_all($contents);
        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!\is_array($token) || \T_NAMESPACE !== $token[0]) {
                continue;
            }
            $namespace = '';
            for ($j = $i + 1; $j < $count; ++$j) {
                $part = $tokens[$j];
                if (\is_string($part)) {
                    if ('{' === $part || ';' === $part) {
                        break;
                    }
                    continue;
                }
                if (\in_array($part[0], [\T_STRING, \T_NAME_QUALIFIED, \T_NS_SEPARATOR], true)) {
                    $namespace .= $part[1];
                }
            }
            $namespace = trim($namespace, '\\');

            return '' === $namespace ? null : $namespace;
        }

        return null;
    }
}
