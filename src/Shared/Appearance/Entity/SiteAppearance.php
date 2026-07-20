<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Entity;

use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton row for operator-customizable look & feel (ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: SiteAppearanceRepository::class)]
#[ORM\Table(name: 'site_appearance')]
class SiteAppearance
{
    public const DEFAULT_BRAND_NAME = 'symfony-beacon';
    public const DEFAULT_BRAND_EYEBROW = 'Error tracking';
    public const DEFAULT_ACCENT = '#1f6f54';
    public const DEFAULT_ACCENT_DEEP = '#134736';
    public const DEFAULT_ACCENT_DARK = '#4aad7f';
    public const DEFAULT_ACCENT_DEEP_DARK = '#6bc49a';

    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 80)]
    private string $brandName = self::DEFAULT_BRAND_NAME;

    #[ORM\Column(length: 80)]
    private string $brandEyebrow = self::DEFAULT_BRAND_EYEBROW;

    #[ORM\Column(length: 7)]
    private string $accentColor = self::DEFAULT_ACCENT;

    #[ORM\Column(length: 7)]
    private string $accentDeepColor = self::DEFAULT_ACCENT_DEEP;

    #[ORM\Column(length: 7)]
    private string $accentColorDark = self::DEFAULT_ACCENT_DARK;

    #[ORM\Column(length: 7)]
    private string $accentDeepColorDark = self::DEFAULT_ACCENT_DEEP_DARK;

    public static function defaults(): self
    {
        return new self();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): self
    {
        $this->brandName = trim($brandName);

        return $this;
    }

    public function getBrandEyebrow(): string
    {
        return $this->brandEyebrow;
    }

    public function setBrandEyebrow(string $brandEyebrow): self
    {
        $this->brandEyebrow = trim($brandEyebrow);

        return $this;
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function setAccentColor(string $accentColor): self
    {
        $this->accentColor = strtolower(trim($accentColor));

        return $this;
    }

    public function getAccentDeepColor(): string
    {
        return $this->accentDeepColor;
    }

    public function setAccentDeepColor(string $accentDeepColor): self
    {
        $this->accentDeepColor = strtolower(trim($accentDeepColor));

        return $this;
    }

    public function getAccentColorDark(): string
    {
        return $this->accentColorDark;
    }

    public function setAccentColorDark(string $accentColorDark): self
    {
        $this->accentColorDark = strtolower(trim($accentColorDark));

        return $this;
    }

    public function getAccentDeepColorDark(): string
    {
        return $this->accentDeepColorDark;
    }

    public function setAccentDeepColorDark(string $accentDeepColorDark): self
    {
        $this->accentDeepColorDark = strtolower(trim($accentDeepColorDark));

        return $this;
    }

    public function resetToDefaults(): self
    {
        $this->brandName = self::DEFAULT_BRAND_NAME;
        $this->brandEyebrow = self::DEFAULT_BRAND_EYEBROW;
        $this->accentColor = self::DEFAULT_ACCENT;
        $this->accentDeepColor = self::DEFAULT_ACCENT_DEEP;
        $this->accentColorDark = self::DEFAULT_ACCENT_DARK;
        $this->accentDeepColorDark = self::DEFAULT_ACCENT_DEEP_DARK;

        return $this;
    }
}
