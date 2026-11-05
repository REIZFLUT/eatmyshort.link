<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Personal access token for the REST API. Only the sha256 hash is stored;
 * the plain token is shown once on creation.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken
{
    public const string PREFIX = 'emsl_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    /** First characters of the token, to recognise it in the list. */
    #[ORM\Column(length: 16)]
    private string $hint;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    private function __construct(User $user, string $name, string $plainToken, ?\DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->name = $name;
        $this->tokenHash = self::hash($plainToken);
        $this->hint = substr($plainToken, 0, 12);
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array{ApiToken, string} the entity and the plain token (show it once) */
    public static function issue(User $user, string $name, ?\DateTimeImmutable $expiresAt = null): array
    {
        $plain = self::PREFIX.bin2hex(random_bytes(24));

        return [new self($user, $name, $plain, $expiresAt), $plain];
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= new \DateTimeImmutable();
    }
}
