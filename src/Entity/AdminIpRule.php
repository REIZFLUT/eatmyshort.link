<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminIpRuleRepository;
use App\Validator\IpOrCidr;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An IP address or CIDR range allowed to open /admin. No rules = no restriction.
 */
#[ORM\Entity(repositoryClass: AdminIpRuleRepository::class)]
#[UniqueEntity(fields: ['cidr'], message: 'ip_rule.cidr.taken')]
class AdminIpRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[IpOrCidr]
    private string $cidr = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $label = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $cidr = '', ?string $label = null)
    {
        $this->cidr = trim($cidr);
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCidr(): string
    {
        return $this->cidr;
    }

    public function setCidr(?string $cidr): static
    {
        $this->cidr = trim((string) $cidr);

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = '' === trim((string) $label) ? null : trim((string) $label);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
