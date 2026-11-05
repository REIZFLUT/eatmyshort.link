<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AdminIpRule;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;

final class InfrastructureTest extends KernelTestCase
{
    public function testSqlitePragmas(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        self::assertSame('wal', strtolower((string) $connection->fetchOne('PRAGMA journal_mode')));
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
    }

    public function testSessionFilesLiveInProjectDirectory(): void
    {
        $container = static::getContainer();

        // Without an explicit save_path sessions end up in /var/lib/php/sessions, which
        // Debian's cron purges after the php.ini gc_maxlifetime (1440s) -> logout after ~24min.
        self::assertSame(
            $container->getParameter('kernel.project_dir').'/var/sessions',
            $container->getParameter('session.save_path'),
        );
        self::assertInstanceOf(
            StrictSessionHandler::class,
            $container->get('session.handler'),
            'the session handler must be session.handler.native_file',
        );
    }

    public function testAdminIpCommand(): void
    {
        $tester = $this->command('app:admin-ip');

        $tester->execute(['action' => 'allow', 'cidr' => '198.51.100.0/24', 'label' => 'Notfall']);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('198.51.100.0/24', $tester->getDisplay());
        self::assertSame(1, static::getContainer()->get('doctrine')->getRepository(AdminIpRule::class)->count([]));

        $tester->execute(['action' => 'allow', 'cidr' => 'kaputt']);
        self::assertSame(2, $tester->getStatusCode());

        $tester->execute(['action' => 'clear']);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('open to every address', $tester->getDisplay());
    }

    public function testUserAndDomainCommands(): void
    {
        $user = $this->command('app:user:create');
        $user->execute(['email' => 'cli@example.com', 'name' => 'CLI', '--admin' => true, '--invite' => true]);
        $user->assertCommandIsSuccessful();
        self::assertStringContainsString('/invite/', $user->getDisplay());

        $domain = $this->command('app:domain:add');
        $domain->execute(['hostname' => 'Kurz.Beispiel.de', '--default' => true]);
        $domain->assertCommandIsSuccessful();
        self::assertStringContainsString('kurz.beispiel.de', $domain->getDisplay());

        $domain->execute(['hostname' => 'admin.emsl.test']);
        self::assertSame(1, $domain->getStatusCode(), 'the admin host cannot become a short domain');
    }

    public function testBackupCommand(): void
    {
        $target = sys_get_temp_dir().'/emsl-backup-'.bin2hex(random_bytes(4)).'.sqlite';
        $tester = $this->command('app:db:backup');
        try {
            $tester->execute(['target' => $target]);
            $tester->assertCommandIsSuccessful();
            $copy = new \PDO('sqlite:'.$target);
            self::assertNotFalse($copy->query('SELECT COUNT(*) FROM link'));
            clearstatcache(true, $target);
            self::assertSame(0o600, fileperms($target) & 0o777, 'the copy contains secrets: owner read/write only');
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testPruneCommand(): void
    {
        $tester = $this->command('app:clicks:prune');
        $tester->execute(['--days' => 30]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('0 clicks', $tester->getDisplay());
    }

    private function command(string $name): CommandTester
    {
        return new CommandTester(new Application(static::bootKernel())->find($name));
    }
}
