<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DomainRepository;
use App\Validator\TargetUrl;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[UniqueEntity(fields: ['hostname'], message: 'domain.hostname.taken')]
class Domain implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Lowercase ASCII hostname (IDNs are stored as punycode). */
    #[ORM\Column(length: 253, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Hostname(requireTld: false, message: 'domain.hostname.invalid')]
    private string $hostname = '';

    #[ORM\Column]
    private bool $active = true;

    /** Preselected domain when creating links. */
    #[ORM\Column]
    private bool $isDefault = false;

    /** Where the bare domain (path "/") redirects to. Null shows a neutral page. */
    #[ORM\Column(length: 2048, nullable: true)]
    #[TargetUrl]
    private ?string $rootRedirectUrl = null;

    /** Where unknown codes redirect to. Null answers with a 404 page. */
    #[ORM\Column(length: 2048, nullable: true)]
    #[TargetUrl]
    private ?string $notFoundUrl = null;

    /** Append the unknown path to notFoundUrl (go.firma.de/foo -> www.firma.de/foo). */
    #[ORM\Column]
    private bool $forwardUnknownPaths = false;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'domains')]
    private Collection $users;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $hostname = '')
    {
        $this->users = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->setHostname($hostname);
    }

    public function __toString(): string
    {
        return $this->hostname;
    }

    public static function normalizeHostname(string $hostname): string
    {
        $hostname = rtrim(mb_strtolower(trim($hostname)), '.');
        if ('' !== $hostname && preg_match('/[^\x20-\x7e]/', $hostname)) {
            $ascii = idn_to_ascii($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $hostname = false === $ascii ? $hostname : $ascii;
        }

        return $hostname;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function setHostname(string $hostname): static
    {
        $this->hostname = self::normalizeHostname($hostname);

        return $this;
    }

    /** Human readable form (punycode decoded). */
    public function getDisplayName(): string
    {
        if (!str_contains($this->hostname, 'xn--')) {
            return $this->hostname;
        }
        $unicode = idn_to_utf8($this->hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return false === $unicode ? $this->hostname : $unicode;
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getRootRedirectUrl(): ?string
    {
        return $this->rootRedirectUrl;
    }

    public function setRootRedirectUrl(?string $url): static
    {
        $this->rootRedirectUrl = '' === trim((string) $url) ? null : trim((string) $url);

        return $this;
    }

    public function getNotFoundUrl(): ?string
    {
        return $this->notFoundUrl;
    }

    public function setNotFoundUrl(?string $url): static
    {
        $this->notFoundUrl = '' === trim((string) $url) ? null : trim((string) $url);

        return $this;
    }

    public function isForwardUnknownPaths(): bool
    {
        return $this->forwardUnknownPaths;
    }

    public function setForwardUnknownPaths(bool $forwardUnknownPaths): static
    {
        $this->forwardUnknownPaths = $forwardUnknownPaths;

        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
