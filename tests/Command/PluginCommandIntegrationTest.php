<?php

declare(strict_types=1);

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Resolves the commands from the real console application, so it also proves they are
 * registered and autowired (the unit tests drive the command objects directly). Uses
 * CommandTester instead of Application::run() because the test environment is quiet
 * (SHELL_VERBOSITY=-1), which would swallow the output.
 */
final class PluginCommandIntegrationTest extends KernelTestCase
{
    public function testBothCommandsRunAgainstTheFixturePlugins(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $list = new CommandTester($application->find('app:plugin:list'));
        self::assertSame(0, $list->execute([]));
        $listDisplay = $list->getDisplay();
        self::assertStringContainsString('probe', $listDisplay);
        self::assertStringContainsString('aktiv', $listDisplay);
        self::assertStringContainsString('deaktiviert', $listDisplay);

        $validate = new CommandTester($application->find('app:plugin:validate'));
        self::assertSame(0, $validate->execute([]), 'only warnings, so the exit code stays 0');
        $validateDisplay = $validate->getDisplay();
        self::assertStringContainsString('unlisted', $validateDisplay);
        self::assertStringContainsString('not listed in the whitelist', $validateDisplay);
    }
}
