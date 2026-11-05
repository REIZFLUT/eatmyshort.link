<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\CredentialRecord;

/**
 * A registered passkey. The WebAuthn fields come from the mapped superclass
 * Webauthn\CredentialRecord (XML mapping shipped with the bundle).
 */
#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential extends CredentialRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public static function fromRecord(CredentialRecord $record, User $user, string $name): self
    {
        $credential = new self(
            $record->publicKeyCredentialId,
            $record->type,
            $record->transports,
            $record->attestationType,
            $record->trustPath,
            $record->aaguid,
            $record->credentialPublicKey,
            $record->userHandle,
            $record->counter,
            $record->otherUI,
            $record->backupEligible,
            $record->backupStatus,
            $record->uvInitialized,
        );
        $credential->user = $user;
        $credential->name = $name;
        $credential->createdAt = new \DateTimeImmutable();

        return $credential;
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

    public function setName(string $name): static
    {
        $this->name = mb_substr(trim($name), 0, 100);

        return $this;
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

    /** Synced passkeys (iCloud, Google, 1Password …) report backup eligibility. */
    public function isSynced(): bool
    {
        return true === $this->backupEligible;
    }
}
