<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Key/value store for settings that admins change at runtime (branding, policies).
 * Read through App\Settings\Settings, never directly.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_setting')]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', length: 64)]
    private string $key;

    #[ORM\Column(name: 'setting_value', type: Types::TEXT, nullable: true)]
    private ?string $value;

    public function __construct(string $key, ?string $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
