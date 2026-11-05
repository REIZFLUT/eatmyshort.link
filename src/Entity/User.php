<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'user.email.taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $displayName = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** Null for users that have not accepted their invitation yet or only use passkeys. */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    /** Random, stable WebAuthn user handle (never the database id or e-mail). */
    #[ORM\Column(length: 64, unique: true)]
    private string $webauthnHandle;

    /** TOTP secret, encrypted at rest (see App\Security\TotpSecretListener). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $totpSecretEncrypted = null;

    private ?string $totpSecret = null;

    #[ORM\Column]
    private bool $totpEnabled = false;

    /** @var list<string> sha256 hashes of the unused backup codes */
    #[ORM\Column(type: Types::JSON)]
    private array $backupCodes = [];

    /** @var Collection<int, Domain> */
    #[ORM\ManyToMany(targetEntity: Domain::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_domain')]
    private Collection $domains;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->webauthnHandle = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, self::ROLE_USER]));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_diff($roles, [self::ROLE_USER])));

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    public function setAdmin(bool $admin): static
    {
        $this->setRoles($admin ? [...$this->roles, self::ROLE_ADMIN] : array_diff($this->roles, [self::ROLE_ADMIN]));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function hasPassword(): bool
    {
        return null !== $this->password;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getWebauthnHandle(): string
    {
        return $this->webauthnHandle;
    }

    public function getTotpSecretEncrypted(): ?string
    {
        return $this->totpSecretEncrypted;
    }

    /** Called by the TOTP setup flow (encrypted value) and the postLoad listener (plain value). */
    public function setTotpSecret(?string $plain, ?string $encrypted): static
    {
        $this->totpSecret = $plain;
        $this->totpSecretEncrypted = $encrypted;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function isTotpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    public function setTotpEnabled(bool $enabled): static
    {
        $this->totpEnabled = $enabled;
        if (!$enabled) {
            $this->totpSecret = null;
            $this->totpSecretEncrypted = null;
            $this->backupCodes = [];
        }

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpEnabled && null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    /** @param list<string> $plainCodes */
    public function replaceBackupCodes(array $plainCodes): static
    {
        $this->backupCodes = array_map(self::hashBackupCode(...), $plainCodes);

        return $this;
    }

    public function countBackupCodes(): int
    {
        return \count($this->backupCodes);
    }

    public function isBackupCode(string $code): bool
    {
        return \in_array(self::hashBackupCode($code), $this->backupCodes, true);
    }

    public function invalidateBackupCode(string $code): void
    {
        $this->backupCodes = array_values(array_diff($this->backupCodes, [self::hashBackupCode($code)]));
    }

    private static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtolower(str_replace([' ', '-'], '', $code)));
    }

    /** @return Collection<int, Domain> */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(Domain $domain): static
    {
        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        $this->domains->removeElement($domain);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function __serialize(): array
    {
        // Keep secrets out of the session: only what the security layer compares on refresh,
        // the password hash itself reduced to crc32c as supported by Symfony's ContextListener.
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'active' => $this->active,
            'password' => null === $this->password ? null : hash('crc32c', $this->password),
            'webauthnHandle' => $this->webauthnHandle,
        ];
    }

    /** @param array{id: ?int, email: string, roles: list<string>, active: bool, password: ?string, webauthnHandle: string} $data */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->roles = $data['roles'];
        $this->active = $data['active'];
        $this->password = $data['password'];
        $this->webauthnHandle = $data['webauthnHandle'];
        $this->domains = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }
}
