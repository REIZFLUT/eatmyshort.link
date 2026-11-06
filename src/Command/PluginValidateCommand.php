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
 * Pre-flight check for config/plugins.yaml and the plugin directories: reports every
 * problem at once instead of stopping at the first one, and fails with exit code 1 when
 * there is an error (warnings alone keep the run green).
 */
#[AsCommand(name: 'app:plugin:validate', description: 'Validates the plugin whitelist and every plugin directory')]
final readonly class PluginValidateCommand
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
            $audit = $this->registry->audit($this->environment);
        } catch (PluginException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        foreach ($audit['warnings'] as $warning) {
            $io->warning($warning);
        }
        foreach ($audit['errors'] as $error) {
            $io->error($error);
        }

        if ([] !== $audit['errors']) {
            $io->error(\sprintf('%d plugin error(s) found; fix them before booting the app.', \count($audit['errors'])));

            return Command::FAILURE;
        }

        $io->success([] === $audit['warnings']
            ? 'No plugin problems found.'
            : \sprintf('No plugin errors found (%d warning(s)).', \count($audit['warnings'])));

        return Command::SUCCESS;
    }
}
