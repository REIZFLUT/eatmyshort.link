<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClickRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One redirect. Stores no raw IP address: only the anonymised network prefix and a
 * daily-salted visitor hash that cannot be linked across days.
 */
#[ORM\Entity(repositoryClass: ClickRepository::class)]
#[ORM\Index(name: 'idx_click_link_day', columns: ['link_id', 'clicked_day'])]
#[ORM\Index(name: 'idx_click_day', columns: ['clicked_day'])]
class Click
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Link $link;

    #[ORM\Column]
    private \DateTimeImmutable $clickedAt;

    /** Local calendar day of the click; makes per-day statistics a portable GROUP BY. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $clickedDay;

    #[ORM\Column(length: 64)]
    private string $visitorHash;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipPrefix = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referrerHost = null;

    #[ORM\Column(length: 16)]
    private string $deviceType = 'unknown';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $os = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $language = null;

    #[ORM\Column]
    private bool $bot = false;

    public function __construct(Link $link, \DateTimeImmutable $clickedAt, string $visitorHash)
    {
        $this->link = $link;
        $this->clickedAt = $clickedAt;
        $this->clickedDay = $clickedAt->setTime(0, 0);
        $this->visitorHash = $visitorHash;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function getClickedAt(): \DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function getClickedDay(): \DateTimeImmutable
    {
        return $this->clickedDay;
    }

    public function getVisitorHash(): string
    {
        return $this->visitorHash;
    }

    public function getIpPrefix(): ?string
    {
        return $this->ipPrefix;
    }

    public function setIpPrefix(?string $ipPrefix): static
    {
        $this->ipPrefix = $ipPrefix;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getReferrerHost(): ?string
    {
        return $this->referrerHost;
    }

    public function setReferrerHost(?string $referrerHost): static
    {
        $this->referrerHost = null === $referrerHost ? null : mb_substr($referrerHost, 0, 255);

        return $this;
    }

    public function getDeviceType(): string
    {
        return $this->deviceType;
    }

    public function setDeviceType(string $deviceType): static
    {
        $this->deviceType = mb_substr($deviceType, 0, 16);

        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function setBrowser(?string $browser): static
    {
        $this->browser = null === $browser ? null : mb_substr($browser, 0, 64);

        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): static
    {
        $this->os = null === $os ? null : mb_substr($os, 0, 64);

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = null === $language ? null : mb_substr($language, 0, 12);

        return $this;
    }

    public function isBot(): bool
    {
        return $this->bot;
    }

    public function setBot(bool $bot): static
    {
        $this->bot = $bot;

        return $this;
    }
}
