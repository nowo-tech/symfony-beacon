<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Entity;

use App\Identity\Entity\User;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Singleton row for operator-customizable look & feel (ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: SiteAppearanceRepository::class)]
#[ORM\Table(name: 'site_appearance')]
class SiteAppearance implements AuditableInterface
{
    use TimestampableTrait;

    public const DEFAULT_BRAND_NAME = 'symfony-beacon';
    public const DEFAULT_BRAND_EYEBROW = 'Error tracking';
    public const DEFAULT_ACCENT = '#1f6f54';
    public const DEFAULT_ACCENT_DEEP = '#134736';
    public const DEFAULT_ACCENT_DARK = '#4aad7f';
    public const DEFAULT_ACCENT_DEEP_DARK = '#6bc49a';
    public const DEFAULT_DANGER = '#b42318';
    public const DEFAULT_DANGER_DARK = '#f97066';
    public const DEFAULT_WARN = '#b54708';
    public const DEFAULT_WARN_DARK = '#fdb022';
    public const DEFAULT_PAPER = '#f3f6f4';
    public const DEFAULT_PAPER_DARK = '#0c1210';
    public const DEFAULT_INK = '#0f1c18';
    public const DEFAULT_INK_DARK = '#e6eee9';
    public const DEFAULT_SURFACE = '#ffffff';
    public const DEFAULT_SURFACE_DARK = '#151c19';

    public const string CORNER_SHARP = 'sharp';
    public const string CORNER_SOFT = 'soft';
    public const string CORNER_ROUNDED = 'rounded';

    /** @var list<string> */
    public const array CORNER_STYLES = [
        self::CORNER_SHARP,
        self::CORNER_SOFT,
        self::CORNER_ROUNDED,
    ];

    public const string BORDER_SUBTLE = 'subtle';
    public const string BORDER_MEDIUM = 'medium';
    public const string BORDER_STRONG = 'strong';

    /** @var list<string> */
    public const array BORDER_STRENGTHS = [
        self::BORDER_SUBTLE,
        self::BORDER_MEDIUM,
        self::BORDER_STRONG,
    ];

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

    #[ORM\Column(length: 7)]
    private string $dangerColor = self::DEFAULT_DANGER;

    #[ORM\Column(length: 7)]
    private string $dangerColorDark = self::DEFAULT_DANGER_DARK;

    #[ORM\Column(length: 7)]
    private string $warnColor = self::DEFAULT_WARN;

    #[ORM\Column(length: 7)]
    private string $warnColorDark = self::DEFAULT_WARN_DARK;

    #[ORM\Column(length: 7)]
    private string $paperColor = self::DEFAULT_PAPER;

    #[ORM\Column(length: 7)]
    private string $paperColorDark = self::DEFAULT_PAPER_DARK;

    #[ORM\Column(length: 7)]
    private string $inkColor = self::DEFAULT_INK;

    #[ORM\Column(length: 7)]
    private string $inkColorDark = self::DEFAULT_INK_DARK;

    #[ORM\Column(length: 7)]
    private string $surfaceColor = self::DEFAULT_SURFACE;

    #[ORM\Column(length: 7)]
    private string $surfaceColorDark = self::DEFAULT_SURFACE_DARK;

    /**
     * Named light-mode palette id, or "custom" when light colors were edited by hand.
     */
    #[ORM\Column(length: 40)]
    private string $themeId = 'beacon';

    /**
     * Named dark-mode palette id, or "custom" when dark colors were edited by hand.
     */
    #[ORM\Column(length: 40)]
    private string $themeIdDark = 'custom';

    /**
     * When true, the legal footer stays pinned to the viewport bottom while content scrolls.
     */
    #[ORM\Column]
    private bool $footerFixed = false;

    /**
     * Corner radius preset: sharp | soft | rounded (cards rounder than controls).
     */
    #[ORM\Column(length: 20)]
    private string $cornerStyle = self::CORNER_SOFT;

    /**
     * How defined UI borders look: subtle | medium | strong (light + dark).
     */
    #[ORM\Column(length: 20)]
    private string $borderStrength = self::BORDER_MEDIUM;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

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

    public function getDangerColor(): string
    {
        return $this->dangerColor;
    }

    public function setDangerColor(string $dangerColor): self
    {
        $this->dangerColor = strtolower(trim($dangerColor));

        return $this;
    }

    public function getDangerColorDark(): string
    {
        return $this->dangerColorDark;
    }

    public function setDangerColorDark(string $dangerColorDark): self
    {
        $this->dangerColorDark = strtolower(trim($dangerColorDark));

        return $this;
    }

    public function getWarnColor(): string
    {
        return $this->warnColor;
    }

    public function setWarnColor(string $warnColor): self
    {
        $this->warnColor = strtolower(trim($warnColor));

        return $this;
    }

    public function getWarnColorDark(): string
    {
        return $this->warnColorDark;
    }

    public function setWarnColorDark(string $warnColorDark): self
    {
        $this->warnColorDark = strtolower(trim($warnColorDark));

        return $this;
    }

    public function getPaperColor(): string
    {
        return $this->paperColor;
    }

    public function setPaperColor(string $paperColor): self
    {
        $this->paperColor = strtolower(trim($paperColor));

        return $this;
    }

    public function getPaperColorDark(): string
    {
        return $this->paperColorDark;
    }

    public function setPaperColorDark(string $paperColorDark): self
    {
        $this->paperColorDark = strtolower(trim($paperColorDark));

        return $this;
    }

    public function getInkColor(): string
    {
        return $this->inkColor;
    }

    public function setInkColor(string $inkColor): self
    {
        $this->inkColor = strtolower(trim($inkColor));

        return $this;
    }

    public function getInkColorDark(): string
    {
        return $this->inkColorDark;
    }

    public function setInkColorDark(string $inkColorDark): self
    {
        $this->inkColorDark = strtolower(trim($inkColorDark));

        return $this;
    }

    public function getSurfaceColor(): string
    {
        return $this->surfaceColor;
    }

    public function setSurfaceColor(string $surfaceColor): self
    {
        $this->surfaceColor = strtolower(trim($surfaceColor));

        return $this;
    }

    public function getSurfaceColorDark(): string
    {
        return $this->surfaceColorDark;
    }

    public function setSurfaceColorDark(string $surfaceColorDark): self
    {
        $this->surfaceColorDark = strtolower(trim($surfaceColorDark));

        return $this;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function setThemeId(string $themeId): self
    {
        $normalized = strtolower(trim($themeId));
        $this->themeId = '' === $normalized ? 'custom' : $normalized;

        return $this;
    }

    public function getThemeIdDark(): string
    {
        return $this->themeIdDark;
    }

    public function setThemeIdDark(string $themeIdDark): self
    {
        $normalized = strtolower(trim($themeIdDark));
        $this->themeIdDark = '' === $normalized ? 'custom' : $normalized;

        return $this;
    }

    public function isFooterFixed(): bool
    {
        return $this->footerFixed;
    }

    public function setFooterFixed(bool $footerFixed): self
    {
        $this->footerFixed = $footerFixed;

        return $this;
    }

    public function getCornerStyle(): string
    {
        return $this->cornerStyle;
    }

    public function setCornerStyle(string $cornerStyle): self
    {
        $normalized = strtolower(trim($cornerStyle));
        $this->cornerStyle = \in_array($normalized, self::CORNER_STYLES, true)
            ? $normalized
            : self::CORNER_SOFT;

        return $this;
    }

    public function getBorderStrength(): string
    {
        return $this->borderStrength;
    }

    public function setBorderStrength(string $borderStrength): self
    {
        $normalized = strtolower(trim($borderStrength));
        $this->borderStrength = \in_array($normalized, self::BORDER_STRENGTHS, true)
            ? $normalized
            : self::BORDER_MEDIUM;

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
        $this->dangerColor = self::DEFAULT_DANGER;
        $this->dangerColorDark = self::DEFAULT_DANGER_DARK;
        $this->warnColor = self::DEFAULT_WARN;
        $this->warnColorDark = self::DEFAULT_WARN_DARK;
        $this->paperColor = self::DEFAULT_PAPER;
        $this->paperColorDark = self::DEFAULT_PAPER_DARK;
        $this->inkColor = self::DEFAULT_INK;
        $this->inkColorDark = self::DEFAULT_INK_DARK;
        $this->surfaceColor = self::DEFAULT_SURFACE;
        $this->surfaceColorDark = self::DEFAULT_SURFACE_DARK;
        $this->themeId = 'beacon';
        $this->themeIdDark = 'custom';
        $this->footerFixed = false;
        $this->cornerStyle = self::CORNER_SOFT;
        $this->borderStrength = self::BORDER_MEDIUM;

        return $this;
    }

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?object $createdBy): void
    {
        $this->createdBy = $createdBy instanceof User ? $createdBy : null;
    }

    public function getUpdatedBy(): ?object
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?object $updatedBy): void
    {
        $this->updatedBy = $updatedBy instanceof User ? $updatedBy : null;
    }
}
