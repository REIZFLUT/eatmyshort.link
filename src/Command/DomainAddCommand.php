<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\HostRegistry;
use App\Entity\Domain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:domain:add', description: 'Registers a short-link domain')]
final readonly class DomainAddCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private HostRegistry $hosts,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Hostname, e.g. go.example.com')] string $hostname,
        #[Option('Where the bare domain redirects to')] ?string $root = null,
        #[Option('Make it the preselected domain')] bool $default = false,
    ): int {
        $domain = new Domain($hostname)->setRootRedirectUrl($root)->setIsDefault($default);
        if ($this->hosts->isAdminHost($domain->getHostname())) {
            $io->error('This host serves the admin UI (APP_ADMIN_HOSTS) and cannot be a short-link domain.');

            return Command::FAILURE;
        }
        $errors = $this->validator->validate($domain);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath().': '.$error->getMessage());
            }

            return Command::FAILURE;
        }
        $this->em->persist($domain);
        $this->em->flush();
        if ($default) {
            $this->em->getRepository(Domain::class)->clearDefaultExcept($domain);
        }
        $io->success(\sprintf('Domain %s added.', $domain->getHostname()));

        return Command::SUCCESS;
    }
}
