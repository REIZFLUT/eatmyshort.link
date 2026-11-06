<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PluginValidateCommand;
use App\Plugin\PluginConfig;
use App\Plugin\PluginRegistry;
use App\Tests\Plugin\TempDirectoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PluginValidateCommandTest extends TestCase
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
     * @param array<string, bool> $enabled name => enabled
     */
    private function registry(array $enabled): PluginRegistry
    {
        $lines = ['plugins:'];
        foreach ($enabled as $name => $isEnabled) {
            $lines[] = '    '.$name.':';
            $lines[] = '        enabled: '.($isEnabled ? 'true' : 'false');
        }
        $config = new PluginConfig($this->writeFile('plugins.yaml', implode("\n", $lines)."\n"));

        return new PluginRegistry($this->tempDir, $config);
    }

    private function tester(PluginRegistry $registry): CommandTester
    {
        return new CommandTester(new Command('app:plugin:validate', new PluginValidateCommand($registry, 'dev')));
    }

    public function testCleanSetupSucceeds(): void
    {
        $registry = $this->registry(['alpha' => true]);
        $this->writeFile('alpha/plugin.yaml', "name: alpha\nversion: 1.0.0\ndescription: A.\n");

        $tester = $this->tester($registry);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No plugin problems found', $tester->getDisplay());
    }

    public function testEnabledPluginWithoutDirectoryFails(): void
    {
        $registry = $this->registry(['ghost' => true]);

        $tester = $this->tester($registry);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('ghost', $display);
        self::assertStringContainsString($this->tempDir.'/ghost', $display);
    }

    public function testBrokenManifestFails(): void
    {
        $registry = $this->registry(['broken' => true]);
        $this->writeFile('broken/plugin.yaml', "name: broken\nversion: [oops\n");

        $tester = $this->tester($registry);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('cannot parse', $tester->getDisplay());
    }

    public function testUnlistedDirectoryIsAWarningNotAnError(): void
    {
        $registry = $this->registry(['alpha' => true]);
        $this->writeFile('alpha/plugin.yaml', "name: alpha\nversion: 1.0.0\ndescription: A.\n");
        $this->writeFile('rogue/plugin.yaml', "name: rogue\nversion: 1.0.0\ndescription: R.\n");

        $tester = $this->tester($registry);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('not listed in the whitelist', $display);
        self::assertStringContainsString('No plugin errors found', $display);
    }

    public function testWorldWritableFileIsAWarning(): void
    {
        $registry = $this->registry(['alpha' => true]);
        $this->writeFile('alpha/plugin.yaml', "name: alpha\nversion: 1.0.0\ndescription: A.\n");
        $file = $this->writeFile('alpha/src/Thing.php', "<?php\n\nnamespace Plugin\\Alpha;\n\nfinal class Thing\n{\n}\n");
        chmod($file, 0o666);

        $tester = $this->tester($registry);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('world-writable', $tester->getDisplay());
    }
}
