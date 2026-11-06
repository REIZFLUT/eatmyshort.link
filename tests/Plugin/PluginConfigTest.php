<?php

declare(strict_types=1);

namespace App\Tests\Plugin;

use App\Plugin\PluginConfig;
use App\Plugin\PluginException;
use PHPUnit\Framework\TestCase;

final class PluginConfigTest extends TestCase
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

    private function config(string $yaml): PluginConfig
    {
        return new PluginConfig($this->writeFile('plugins.yaml', $yaml));
    }

    public function testMissingFileMeansNoPlugins(): void
    {
        $config = new PluginConfig($this->tempDir.'/does-not-exist.yaml');

        self::assertFalse($config->exists());
        self::assertSame([], $config->entries('dev'));
    }

    public function testReadsNameEnabledAndSettings(): void
    {
        $config = $this->config(<<<'YAML'
            plugins:
                example-utm:
                    enabled: true
                    settings:
                        campaign: newsletter
            YAML);

        $entries = $config->entries('dev');

        self::assertSame(['example-utm'], array_keys($entries));
        self::assertTrue($entries['example-utm']['enabled']);
        self::assertSame(['campaign' => 'newsletter'], $entries['example-utm']['settings']);
    }

    public function testWhenEnvEnablesAPluginButKeepsBaseSettings(): void
    {
        $config = $this->config(<<<'YAML'
            plugins:
                probe:
                    enabled: false
                    settings: {greeting: hello, level: 1}
            when@test:
                plugins:
                    probe:
                        enabled: true
                        settings: {level: 2}
            YAML);

        self::assertFalse($config->entries('dev')['probe']['enabled']);

        $test = $config->entries('test')['probe'];
        self::assertTrue($test['enabled']);
        self::assertSame(['greeting' => 'hello', 'level' => 2], $test['settings']);
    }

    public function testWhenEnvCanAddAPluginThatIsNotInTheBase(): void
    {
        $config = $this->config(<<<'YAML'
            plugins: {}
            when@test:
                plugins:
                    extra:
                        enabled: true
            YAML);

        self::assertSame([], $config->entries('dev'));
        self::assertSame(['extra'], array_keys($config->entries('test')));
        self::assertTrue($config->entries('test')['extra']['enabled']);
    }

    public function testBrokenYamlThrows(): void
    {
        $config = $this->config("plugins:\n    probe:\n        enabled: [oops\n");

        $this->expectException(PluginException::class);
        $config->entries('dev');
    }

    public function testInvalidPluginNameThrows(): void
    {
        $config = $this->config(<<<'YAML'
            plugins:
                'Bad Name':
                    enabled: true
            YAML);

        $this->expectException(PluginException::class);
        $config->entries('dev');
    }

    public function testNonBooleanEnabledThrows(): void
    {
        $config = $this->config(<<<'YAML'
            plugins:
                probe:
                    enabled: 'yes'
            YAML);

        $this->expectException(PluginException::class);
        $config->entries('dev');
    }
}
