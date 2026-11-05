<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ClickRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:clicks:prune', description: 'Deletes clicks older than the retention period (run daily from cron)')]
final readonly class ClicksPruneCommand
{
    public function __construct(
        private ClickRepository $clicks,
        #[Autowire(env: 'int:default::CLICK_RETENTION_DAYS')]
        private ?int $retentionDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Override CLICK_RETENTION_DAYS')] ?int $days = null): int
    {
        $days ??= $this->retentionDays ?? 0;
        if ($days <= 0) {
            $io->note('Retention is disabled (0 days); nothing deleted.');

            return Command::SUCCESS;
        }

        $deleted = $this->clicks->deleteOlderThan(new \DateTimeImmutable('today')->modify("-$days days"));
        $io->success(\sprintf('%d clicks older than %d days deleted.', $deleted, $days));

        return Command::SUCCESS;
    }
}
