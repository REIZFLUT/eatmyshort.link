<?php

declare(strict_types=1);

namespace App\Tests\Plugin;

use App\Plugin\PluginConfig;
use App\Plugin\PluginException;
use App\Plugin\PluginRegistry;
use PHPUnit\Framework\TestCase;

final class PluginRegistryTest extends TestCase
{
    use TempDirectoryTrait;

    protected function setUp(): void
    {
        $this->createTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir();
    }

    /**
     * @param array<string, array{enabled?: bool, settings?: array<string, mixed>}> $plugins
     */
    private function registryFor(array $plugins): PluginRegistry
    {
        $lines = ['plugins:'];
        foreach ($plugins as $name => $entry) {
            $lines[] = '    '.$name.':';
            $lines[] = '        enabled: '.($entry['enabled'] ?? true ? 'true' : 'false');
        }
        $config = new PluginConfig($this->writeFile('plugins.yaml', implode("\n", $lines)."\n"));

        return new PluginRegistry($this->tempDir, $config);
    }

    /**
     * @param array<string, string> $classes file name => PHP source
     */
    private function addPlugin(string $name, string $manifest, array $classes = []): void
    {
        $this->writeFile($name.'/plugin.yaml', $manifest);
        $this->writeFile($name.'/src/.gitkeep', '');
        foreach ($classes as $file => $contents) {
            $this->writeFile($name.'/src/'.$file, $contents);
        }
    }

    private function manifest(string $name, string $version = '1.0.0', string $description = 'Fixture plugin.', ?string $requiresApi = null): string
    {
        $yaml = "name: $name\nversion: $version\ndescription: $description\n";
        if (null !== $requiresApi) {
            $yaml .= "requires:\n    api: '$requiresApi'\n";
        }

        return $yaml;
    }

    public function testActiveReturnsEnabledValidPlugins(): void
    {
        $registry = $this->registryFor(['alpha' => ['enabled' => true], 'beta' => ['enabled' => false]]);
        $this->addPlugin('alpha', $this->manifest('alpha', '3.2.1'));
        $this->addPlugin('beta', $this->manifest('beta'));

        $active = $registry->active('dev');

        self::assertCount(1, $active);
        self::assertSame('alpha', $active[0]->name);
        self::assertSame('3.2.1', $active[0]->version);
        self::assertSame($this->tempDir.'/alpha', $active[0]->directory);
    }

    public function testDisabledPluginIsNotActiveEvenWhenItsDirectoryIsMissing(): void
    {
        $registry = $this->registryFor(['ghost' => ['enabled' => false]]);

        self::assertSame([], $registry->active('dev'));
        self::assertSame([], $registry->audit('dev')['errors']);
    }

    public function testEnabledPluginWithoutDirectoryThrowsWithThePath(): void
    {
        $registry = $this->registryFor(['missing' => ['enabled' => true]]);

        try {
            $registry->active('dev');
            self::fail('Expected a PluginException.');
        } catch (PluginException $exception) {
            self::assertStringContainsString('missing', $exception->getMessage());
            self::assertStringContainsString($this->tempDir.'/missing', $exception->getMessage());
        }
    }

    public function testBrokenManifestThrows(): void
    {
        $registry = $this->registryFor(['broken' => ['enabled' => true]]);
        $this->writeFile('broken/plugin.yaml', "name: broken\nversion: [oops\n");

        $this->expectException(PluginException::class);
        $registry->active('dev');
    }

    public function testMissingRequiredFieldThrows(): void
    {
        $registry = $this->registryFor(['nofields' => ['enabled' => true]]);
        $this->writeFile('nofields/plugin.yaml', "name: nofields\nversion: 1.0.0\n");

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageMatches('/description/');
        $registry->active('dev');
    }

    public function testDeclaredNameMustMatchTheDirectory(): void
    {
        $registry = $this->registryFor(['actual' => ['enabled' => true]]);
        $this->writeFile('actual/plugin.yaml', $this->manifest('other'));

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageMatches('/does not match its directory/');
        $registry->active('dev');
    }

    public function testUnsatisfiableRequiresApiThrows(): void
    {
        $registry = $this->registryFor(['future' => ['enabled' => true]]);
        $this->writeFile('future/plugin.yaml', $this->manifest('future', requiresApi: '>=9.0'));

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageMatches('/requires plugin API/');
        $registry->active('dev');
    }

    public function testSatisfiableRequiresApiIsAccepted(): void
    {
        $registry = $this->registryFor(['ok1' => ['enabled' => true], 'ok2' => ['enabled' => true]]);
        $this->writeFile('ok1/plugin.yaml', $this->manifest('ok1', requiresApi: '>=1.0'));
        $this->writeFile('ok2/plugin.yaml', $this->manifest('ok2', requiresApi: '^1.0'));

        self::assertCount(2, $registry->active('dev'));
    }

    public function testUnlistedDirectoryIsIgnoredAndReportedAsWarning(): void
    {
        $registry = $this->registryFor(['listed' => ['enabled' => true]]);
        $this->addPlugin('listed', $this->manifest('listed'));
        $this->addPlugin('rogue', $this->manifest('rogue'), ['Rogue.php' => "<?php\n\nnamespace Plugin\\Rogue;\n\nfinal class Rogue\n{\n}\n"]);

        self::assertSame(['rogue'], $registry->unlisted('dev'));
        // Only the listed plugin loads; the unlisted one never becomes active.
        self::assertCount(1, $registry->active('dev'));

        $audit = $registry->audit('dev');
        self::assertSame([], $audit['errors']);
        self::assertCount(1, $audit['warnings']);
        self::assertStringContainsString('rogue', $audit['warnings'][0]);
    }

    public function testAuditCollectsErrorsWithoutThrowing(): void
    {
        $registry = $this->registryFor(['gone' => ['enabled' => true], 'mismatch' => ['enabled' => true]]);
        $this->writeFile('mismatch/plugin.yaml', $this->manifest('different'));

        $audit = $registry->audit('dev');

        self::assertCount(2, $audit['errors']);
        self::assertStringContainsString('gone', implode("\n", $audit['errors']));
        self::assertStringContainsString('does not match', implode("\n", $audit['errors']));
    }

    public function testAuditWarnsAboutClassesOutsideTheExpectedNamespace(): void
    {
        $registry = $this->registryFor(['nsplug' => ['enabled' => true]]);
        $this->writeFile('nsplug/plugin.yaml', $this->manifest('nsplug'));
        $this->writeFile('nsplug/src/Wrong.php', "<?php\n\nnamespace Plugin\\Elsewhere;\n\nfinal class Wrong\n{\n}\n");

        $audit = $registry->audit('dev');

        self::assertSame([], $audit['errors']);
        self::assertCount(1, $audit['warnings']);
        self::assertStringContainsString('outside "Plugin\\Nsplug\\"', $audit['warnings'][0]);
    }

    public function testStatusReportsActiveDisabledAndNotFound(): void
    {
        $registry = $this->registryFor(['alpha' => ['enabled' => true], 'beta' => ['enabled' => false], 'gamma' => ['enabled' => true]]);
        $this->writeFile('alpha/plugin.yaml', $this->manifest('alpha', '1.2.3'));
        $this->writeFile('beta/plugin.yaml', $this->manifest('beta', '0.4.0'));

        $byName = [];
        foreach ($registry->status('dev') as $row) {
            $byName[$row['name']] = $row;
        }

        self::assertSame('aktiv', $byName['alpha']['state']);
        self::assertSame('1.2.3', $byName['alpha']['version']);
        self::assertSame('deaktiviert', $byName['beta']['state']);
        self::assertSame('nicht gefunden', $byName['gamma']['state']);
    }

    public function testNamespaceDerivation(): void
    {
        self::assertSame('Plugin\\ExampleUtm', PluginRegistry::namespaceFor('example-utm'));
        self::assertSame('Plugin\\Probe', PluginRegistry::namespaceFor('probe'));
    }

    public function testNameValidation(): void
    {
        self::assertTrue(PluginRegistry::isValidName('probe'));
        self::assertTrue(PluginRegistry::isValidName('example-utm-2'));
        self::assertFalse(PluginRegistry::isValidName('ab'));
        self::assertFalse(PluginRegistry::isValidName('Upper-Case'));
        self::assertFalse(PluginRegistry::isValidName('under_score'));
        self::assertFalse(PluginRegistry::isValidName(str_repeat('a', 41)));
    }

    // --- activeCached(): the per-request memo of the kernel -------------

    public function testCachedResultMatchesTheFreshOneAndIsReused(): void
    {
        $this->addPlugin('alpha', $this->manifest('alpha', '1.2.0'));
        $registry = $this->registryFor(['alpha' => []]);
        $cache = $this->tempDir.'/cache/plugins.cache';

        $first = $registry->activeCached('prod', $cache);
        self::assertEquals($registry->active('prod'), $first);
        self::assertFileExists($cache);

        // Tamper with the stored payload: if the second call returns the tampered value, it was
        // served from the cache instead of parsing the YAML again.
        $data = unserialize((string) file_get_contents($cache));
        self::assertIsArray($data);
        $data['plugins'][0]['description'] = 'from the cache';
        file_put_contents($cache, serialize($data));

        self::assertSame('from the cache', $registry->activeCached('prod', $cache)[0]->description);
    }

    public function testChangedWhitelistOrManifestInvalidatesTheCache(): void
    {
        $this->addPlugin('alpha', $this->manifest('alpha', '1.0.0'));
        $this->addPlugin('beta', $this->manifest('beta'));
        $cache = $this->tempDir.'/cache/plugins.cache';

        self::assertCount(1, $this->registryFor(['alpha' => [], 'beta' => ['enabled' => false]])->activeCached('prod', $cache));
        self::assertCount(2, $this->registryFor(['alpha' => [], 'beta' => []])->activeCached('prod', $cache), 'whitelist edited');

        $this->writeFile('alpha/plugin.yaml', $this->manifest('alpha', '1.0.1'));
        $plugins = $this->registryFor(['alpha' => [], 'beta' => []])->activeCached('prod', $cache);
        self::assertSame('1.0.1', $plugins[0]->version, 'manifest edited');
    }

    public function testCacheNeverHidesABrokenPlugin(): void
    {
        $this->addPlugin('alpha', $this->manifest('alpha'));
        $registry = $this->registryFor(['alpha' => []]);
        $cache = $this->tempDir.'/cache/plugins.cache';
        self::assertCount(1, $registry->activeCached('prod', $cache));

        unlink($this->tempDir.'/alpha/plugin.yaml');

        $this->expectException(PluginException::class);
        $registry->activeCached('prod', $cache);
    }

    public function testUnreadableCacheFallsBackToTheWhitelist(): void
    {
        $this->addPlugin('alpha', $this->manifest('alpha'));
        $cache = $this->writeFile('cache/plugins.cache', 'not serialized at all');

        self::assertCount(1, $this->registryFor(['alpha' => []])->activeCached('prod', $cache));
    }
}
