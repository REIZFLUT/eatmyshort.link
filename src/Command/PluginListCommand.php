<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\PluginException;
use App\Plugin\PluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shows what config/plugins.yaml asks for and what the app actually loaded.
 */
#[AsCommand(name: 'app:plugin:list', description: 'Lists whitelisted plugins and whether they are loaded')]
final readonly class PluginListCommand
{
    public function __construct(
        private PluginRegistry $registry,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $rows = $this->registry->status($this->environment);
        } catch (PluginException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $rows) {
            $io->note(\sprintf('No plugins are listed in "%s".', $this->registry->config()->file()));

            return Command::SUCCESS;
        }

        $table = array_map(
            static fn (array $row): array => [$row['name'], $row['version'] ?? '?', $row['state'], $row['requires'] ?? '-', $row['directory']],
            $rows,
        );
        $io->table(['Name', 'Version', 'State', 'requires.api', 'Directory'], $table);

        return Command::SUCCESS;
    }
}
