<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

trait InstanceSettingsMercureFields
{
    /** Enables the live-alert facade in {@see \App\Shared\Mercure\ConfiguredMercure}. */
    #[ORM\Column(options: ['default' => false])]
    private bool $mercureEnabled = false;

    /** Encrypted Mercure publish URL override for {@see \App\Shared\Mercure\ConfiguredMercure}. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercureUrl = null;

    /** Encrypted Mercure browser URL override for {@see \App\Shared\Mercure\ConfiguredMercure}. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercurePublicUrl = null;

    /** Encrypted Mercure JWT secret override for {@see \App\Shared\Mercure\ConfiguredMercure}. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercureJwtSecret = null;

    public function isMercureEnabled(): bool
    {
        return $this->mercureEnabled;
    }

    public function setMercureEnabled(bool $mercureEnabled): self
    {
        $this->mercureEnabled = $mercureEnabled;

        return $this;
    }

    public function getMercureUrl(): ?string
    {
        return $this->mercureUrl;
    }

    public function setMercureUrl(?string $mercureUrl): self
    {
        if (null === $mercureUrl) {
            $this->mercureUrl = null;

            return $this;
        }

        $trimmed = trim($mercureUrl);
        $this->mercureUrl = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getMercurePublicUrl(): ?string
    {
        return $this->mercurePublicUrl;
    }

    public function setMercurePublicUrl(?string $mercurePublicUrl): self
    {
        if (null === $mercurePublicUrl) {
            $this->mercurePublicUrl = null;

            return $this;
        }

        $trimmed = trim($mercurePublicUrl);
        $this->mercurePublicUrl = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getMercureJwtSecret(): ?string
    {
        return $this->mercureJwtSecret;
    }

    public function setMercureJwtSecret(?string $mercureJwtSecret): self
    {
        if (null === $mercureJwtSecret) {
            $this->mercureJwtSecret = null;

            return $this;
        }

        $trimmed = trim($mercureJwtSecret);
        $this->mercureJwtSecret = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasMercureJwtSecret(): bool
    {
        return null !== $this->mercureJwtSecret && '' !== $this->mercureJwtSecret;
    }
}
