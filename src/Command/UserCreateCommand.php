<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ResetToken;
use App\Entity\User;
use App\Mail\AccountMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create', description: 'Creates a user (use --admin for the first administrator)')]
final readonly class UserCreateCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
        private AccountMailer $mailer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail address')] string $email,
        #[Argument('Display name')] ?string $name = null,
        #[Option('Grant administrator rights')] bool $admin = false,
        #[Option('Send an invitation link instead of asking for a password')] bool $invite = false,
    ): int {
        if (null !== $this->users->findOneByEmail($email)) {
            $io->error('A user with this e-mail address already exists.');

            return Command::FAILURE;
        }

        $user = new User()->setEmail($email)->setDisplayName($name ?? strstr($email, '@', true) ?: $email)->setAdmin($admin);
        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath().': '.$error->getMessage());
            }

            return Command::FAILURE;
        }

        if (!$invite) {
            $password = (string) $io->askHidden('Password (min. 12 characters)', static function (?string $value): string {
                if (null === $value || mb_strlen($value) < 12) {
                    throw new \RuntimeException('The password needs at least 12 characters.');
                }

                return $value;
            });
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $this->em->persist($user);
        $this->em->flush();

        if ($invite) {
            $result = $this->mailer->sendAccountLink($user, ResetToken::PURPOSE_INVITE);
            $io->writeln($result['sent'] ? 'Invitation sent.' : 'Mail could not be sent; share this link:');
            $io->writeln($result['url']);
        }
        $io->success(\sprintf('%s %s created.', $admin ? 'Administrator' : 'User', $user->getEmail()));

        return Command::SUCCESS;
    }
}
