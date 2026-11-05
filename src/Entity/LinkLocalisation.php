<?php

declare(strict_types=1);

namespace App\Entity;

use App\Validator\TargetUrl;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alternative target for visitors whose Accept-Language matches (e.g. "en" or "de-at").
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_localisation_link_language', columns: ['link_id', 'language'])]
class LinkLocalisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'localisations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Link $link = null;

    /** Lowercase BCP 47 tag: primary language with optional region ("en", "de-at"). */
    #[ORM\Column(length: 12)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', message: 'localisation.language.invalid')]
    private string $language = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[TargetUrl]
    private string $targetUrl = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLink(): ?Link
    {
        return $this->link;
    }

    public function setLink(?Link $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = str_replace('_', '-', mb_strtolower(trim((string) $language)));

        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = trim((string) $targetUrl);

        return $this;
    }
}
