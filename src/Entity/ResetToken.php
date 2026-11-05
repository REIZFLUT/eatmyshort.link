<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One-time link for invitations and password resets (selector/verifier split,
 * only the verifier hash is stored, compared in constant time).
 */
#[ORM\Entity(repositoryClass: ResetTokenRepository::class)]
class ResetToken
{
    public const string PURPOSE_INVITE = 'invite';
    public const string PURPOSE_RESET = 'reset';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 24, unique: true)]
    private string $selector;

    #[ORM\Column(length: 64)]
    private string $verifierHash;

    #[ORM\Column(length: 10)]
    private string $purpose;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private function __construct(User $user, string $purpose, string $selector, string $verifier, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->purpose = $purpose;
        $this->selector = $selector;
        $this->verifierHash = hash('sha256', $verifier);
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array{ResetToken, string} entity and the public token "selector.verifier" */
    public static function issue(User $user, string $purpose, \DateInterval $lifetime): array
    {
        $selector = bin2hex(random_bytes(12));
        $verifier = bin2hex(random_bytes(24));
        $token = new self($user, $purpose, $selector, $verifier, new \DateTimeImmutable()->add($lifetime));

        return [$token, $selector.'.'.$verifier];
    }

    /** @return array{string, string}|null */
    public static function split(string $publicToken): ?array
    {
        $parts = explode('.', $publicToken, 2);
        if (2 !== \count($parts) || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    public function matches(string $verifier): bool
    {
        return hash_equals($this->verifierHash, hash('sha256', $verifier));
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
