<?php

declare(strict_types=1);

namespace App\Tests\Plugin;

use App\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * The compiled container belongs to one set of enabled plugins: a changed set must not reuse a
 * container compiled for another one (in production nothing checks the cache for freshness,
 * so a disabled plugin's listeners stayed wired while its classes were no longer autoloaded).
 */
final class KernelPluginFingerprintTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->createTempDir();
        $this->server = $_SERVER;
        $_SERVER['APP_PLUGIN_DIR'] = $this->tempDir;
        $this->writeFile('alpha/plugin.yaml', "name: alpha\nversion: 1.0.0\ndescription: A.\n");
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $this->removeTempDir();
    }

    public function testContainerClassFollowsTheActivePluginSet(): void
    {
        $none = $this->containerClassFor("plugins:\n    alpha:\n        enabled: false\n");
        $enabled = $this->containerClassFor("plugins:\n    alpha:\n        enabled: true\n");
        $configured = $this->containerClassFor("plugins:\n    alpha:\n        enabled: true\n        settings: {mode: other}\n");

        self::assertSame('App_KernelTestContainer', $none, 'without plugins the framework default is kept');
        self::assertStringStartsWith('App_KernelTestContainerP', $enabled);
        self::assertNotSame($enabled, $configured, 'new settings mean a new container (they are container parameters)');
        self::assertSame($enabled, $this->containerClassFor("plugins:\n    alpha:\n        enabled: true\n"), 'the same set finds the same container');
    }

    private function containerClassFor(string $whitelist): string
    {
        $_SERVER['APP_PLUGIN_CONFIG'] = $this->writeFile('plugins-'.md5($whitelist).'.yaml', $whitelist);
        $kernel = new Kernel('test', false);

        $method = new \ReflectionMethod($kernel, 'getContainerClass');

        return (string) $method->invoke($kernel);
    }
}
