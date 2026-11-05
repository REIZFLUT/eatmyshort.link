<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AdminIpRule;
use App\Repository\AdminIpRuleRepository;
use App\Security\AdminIpPolicy;
use App\Validator\IpOrCidrValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Break-glass access to the admin IP allowlist from the shell.
 */
#[AsCommand(name: 'app:admin-ip', description: 'Lists or changes the IP rules for /admin (list, allow <cidr> [label], remove <cidr>, clear)')]
final readonly class AdminIpCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdminIpRuleRepository $rules,
        private AdminIpPolicy $policy,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('list, allow, remove or clear', suggestedValues: ['list', 'allow', 'remove', 'clear'])] string $action = 'list',
        #[Argument('IP address or CIDR range')] ?string $cidr = null,
        #[Argument('Label for "allow"')] ?string $label = null,
    ): int {
        switch ($action) {
            case 'allow':
                if (null === $cidr || !IpOrCidrValidator::isValid($cidr)) {
                    $io->error('Give a valid IP address or CIDR range.');

                    return Command::INVALID;
                }
                if (!\in_array($cidr, $this->rules->findAllCidrs(), true)) {
                    $this->em->persist(new AdminIpRule($cidr, $label ?? 'CLI'));
                    $this->em->flush();
                }
                $io->success("$cidr may open /admin.");
                break;
            case 'remove':
                $rule = null === $cidr ? null : $this->rules->findOneBy(['cidr' => $cidr]);
                if (null === $rule) {
                    $io->error('No such rule.');

                    return Command::FAILURE;
                }
                $this->em->remove($rule);
                $this->em->flush();
                $io->success("$cidr removed.");
                break;
            case 'clear':
                foreach ($this->rules->findAll() as $rule) {
                    $this->em->remove($rule);
                }
                $this->em->flush();
                $io->success('All database rules removed.');
                break;
            case 'list':
                break;
            default:
                $io->error('Unknown action.');

                return Command::INVALID;
        }

        $rows = array_map(static fn (string $c): array => [$c, 'env (ADMIN_IP_ALLOWLIST)'], $this->policy->getEnvRules());
        foreach ($this->rules->findAllOrdered() as $rule) {
            $rows[] = [$rule->getCidr(), $rule->getLabel() ?? ''];
        }
        $io->table(['Rule', 'Source / label'], $rows);
        $io->writeln($this->policy->isRestricted() ? '/admin is restricted to these addresses.' : '/admin is open to every address (no rules).');

        return Command::SUCCESS;
    }
}
