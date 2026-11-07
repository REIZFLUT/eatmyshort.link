<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PluginListCommand;
use App\Plugin\PluginConfig;
use App\Plugin\PluginRegistry;
use App\Tests\Plugin\TempDirectoryTrait;
use App\Tests\Support\ConsoleDisplayAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PluginListCommandTest extends TestCase
{
    use ConsoleDisplayAssertions;
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

    public function testListsActiveDisabledAndMissingWithDetails(): void
    {
        $registry = $this->registry(['alpha' => true, 'beta' => false, 'gamma' => true]);
        $this->writeFile('alpha/plugin.yaml', "name: alpha\nversion: 1.2.3\ndescription: A.\nrequires:\n    api: '>=1.0'\n");
        $this->writeFile('beta/plugin.yaml', "name: beta\nversion: 0.4.0\ndescription: B.\n");

        $tester = new CommandTester(new Command('app:plugin:list', new PluginListCommand($registry, 'dev')));
        $status = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $status);
        self::assertDisplayContains('alpha', $display);
        self::assertDisplayContains('1.2.3', $display);
        self::assertDisplayContains('aktiv', $display);
        self::assertDisplayContains('deaktiviert', $display);
        self::assertDisplayContains('nicht gefunden', $display);
        self::assertDisplayContains('>=1.0', $display);
        self::assertDisplayContains($this->tempDir.'/gamma', $display);
    }

    public function testEmptyWhitelistNotesThatNothingIsListed(): void
    {
        $registry = $this->registry([]);

        $tester = new CommandTester(new Command('app:plugin:list', new PluginListCommand($registry, 'dev')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertDisplayContains('No plugins are listed', $tester->getDisplay());
    }
}
