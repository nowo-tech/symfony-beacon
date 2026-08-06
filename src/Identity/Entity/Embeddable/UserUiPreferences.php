<?php

declare(strict_types=1);

namespace App\Identity\Entity\Embeddable;

use App\Identity\Tour\ProductTourPage;
use App\Identity\UserDisplayPreferenceDefaults;
use App\Issues\IssuePanelIds;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * UI / display preferences stored as columns on {@see \App\Identity\Entity\User}
 * (`columnPrefix: false` keeps historical `preferred_*` / tour / push column names).
 */
#[ORM\Embeddable]
class UserUiPreferences
{
    /** Preferred UI locale (`en` / `es` / …); null only for legacy / anonymized rows. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredLocale = null;

    /** Preferred color theme (`light` / `dark`). */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredTheme = UserDisplayPreferenceDefaults::THEME;

    /** Main content width: `content` (centered max-width) or `full`. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredContentWidth = UserDisplayPreferenceDefaults::CONTENT_WIDTH;

    /** UI density: `comfortable` (default) or `compact`. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredUiDensity = UserDisplayPreferenceDefaults::UI_DENSITY;

    /** Motion preference: `system` (follow OS), `reduce`, or `full`. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredMotion = UserDisplayPreferenceDefaults::MOTION;

    /** Root font scale: `sm` | `md` (default) | `lg`. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredFontScale = UserDisplayPreferenceDefaults::FONT_SCALE;

    /** Contrast: `system` (follow device) or `more`. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredContrast = UserDisplayPreferenceDefaults::CONTRAST;

    /** Desktop sidebar default: `expanded` (default) or `collapsed`. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredSidebar = UserDisplayPreferenceDefaults::SIDEBAR;

    /**
     * Issue/event panel ids that should start collapsed (browser can override via localStorage).
     *
     * @var list<string>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $preferredCollapsedIssuePanels = null;

    /** When set, all product tours are suppressed (Account → Display checkbox). */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $productTourSeenAt = null;

    /**
     * Tour page ids already completed (e.g. dashboard, project_issues, admin).
     *
     * @var list<string>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $productTourSeenPages = null;

    /** Opt-in for PWA / browser push alerts on new issues in associated projects. */
    #[ORM\Column(options: ['default' => false])]
    private bool $pushNotificationsEnabled = false;

    public function getPreferredLocale(): string
    {
        return $this->preferredLocale ?? UserDisplayPreferenceDefaults::LOCALE;
    }

    public function getPreferredLocaleRaw(): ?string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(?string $preferredLocale): self
    {
        $normalized = null !== $preferredLocale ? strtolower(trim($preferredLocale)) : null;
        $this->preferredLocale = '' !== $normalized ? $normalized : null;

        return $this;
    }

    public function getPreferredTheme(): string
    {
        return $this->preferredTheme ?? UserDisplayPreferenceDefaults::THEME;
    }

    public function getPreferredThemeRaw(): ?string
    {
        return $this->preferredTheme;
    }

    public function setPreferredTheme(?string $preferredTheme): self
    {
        $normalized = null !== $preferredTheme ? strtolower(trim($preferredTheme)) : null;
        if (null !== $normalized && !\in_array($normalized, ['light', 'dark'], true)) {
            $normalized = null;
        }
        $this->preferredTheme = $normalized;

        return $this;
    }

    public function getPreferredContentWidth(): string
    {
        return 'full' === $this->preferredContentWidth ? 'full' : UserDisplayPreferenceDefaults::CONTENT_WIDTH;
    }

    public function getPreferredContentWidthRaw(): ?string
    {
        return $this->preferredContentWidth;
    }

    public function setPreferredContentWidth(?string $preferredContentWidth): self
    {
        $normalized = null !== $preferredContentWidth ? strtolower(trim($preferredContentWidth)) : null;
        if (!\in_array($normalized, ['content', 'full'], true)) {
            $normalized = null;
        }
        $this->preferredContentWidth = $normalized;

        return $this;
    }

    public function getPreferredUiDensity(): string
    {
        return 'compact' === $this->preferredUiDensity ? 'compact' : UserDisplayPreferenceDefaults::UI_DENSITY;
    }

    public function getPreferredUiDensityRaw(): ?string
    {
        return $this->preferredUiDensity;
    }

    public function setPreferredUiDensity(?string $preferredUiDensity): self
    {
        $normalized = null !== $preferredUiDensity ? strtolower(trim($preferredUiDensity)) : null;
        if (!\in_array($normalized, ['comfortable', 'compact'], true)) {
            $normalized = null;
        }
        $this->preferredUiDensity = $normalized;

        return $this;
    }

    public function getPreferredMotion(): string
    {
        return $this->preferredMotion ?? UserDisplayPreferenceDefaults::MOTION;
    }

    public function getPreferredMotionRaw(): ?string
    {
        return $this->preferredMotion;
    }

    public function setPreferredMotion(?string $preferredMotion): self
    {
        $normalized = null !== $preferredMotion ? strtolower(trim($preferredMotion)) : null;
        if (null !== $normalized && !\in_array($normalized, ['system', 'reduce', 'full'], true)) {
            $normalized = null;
        }
        $this->preferredMotion = $normalized;

        return $this;
    }

    public function getPreferredFontScale(): string
    {
        return \in_array($this->preferredFontScale, ['sm', 'lg'], true)
            ? $this->preferredFontScale
            : UserDisplayPreferenceDefaults::FONT_SCALE;
    }

    public function getPreferredFontScaleRaw(): ?string
    {
        return $this->preferredFontScale;
    }

    public function setPreferredFontScale(?string $preferredFontScale): self
    {
        $normalized = null !== $preferredFontScale ? strtolower(trim($preferredFontScale)) : null;
        if (!\in_array($normalized, ['sm', 'md', 'lg'], true)) {
            $normalized = null;
        }
        $this->preferredFontScale = $normalized;

        return $this;
    }

    public function getPreferredContrast(): string
    {
        return $this->preferredContrast ?? UserDisplayPreferenceDefaults::CONTRAST;
    }

    public function getPreferredContrastRaw(): ?string
    {
        return $this->preferredContrast;
    }

    public function setPreferredContrast(?string $preferredContrast): self
    {
        $normalized = null !== $preferredContrast ? strtolower(trim($preferredContrast)) : null;
        if (null !== $normalized && !\in_array($normalized, ['system', 'more'], true)) {
            $normalized = null;
        }
        $this->preferredContrast = $normalized;

        return $this;
    }

    public function getPreferredSidebar(): string
    {
        return 'collapsed' === $this->preferredSidebar ? 'collapsed' : UserDisplayPreferenceDefaults::SIDEBAR;
    }

    public function getPreferredSidebarRaw(): ?string
    {
        return $this->preferredSidebar;
    }

    public function setPreferredSidebar(?string $preferredSidebar): self
    {
        $normalized = null !== $preferredSidebar ? strtolower(trim($preferredSidebar)) : null;
        if (!\in_array($normalized, ['expanded', 'collapsed'], true)) {
            $normalized = null;
        }
        $this->preferredSidebar = $normalized;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPreferredCollapsedIssuePanels(): array
    {
        if (null === $this->preferredCollapsedIssuePanels) {
            return IssuePanelIds::defaultCollapsed();
        }

        return IssuePanelIds::sanitize($this->preferredCollapsedIssuePanels);
    }

    /**
     * @param list<string>|null $preferredCollapsedIssuePanels
     */
    public function setPreferredCollapsedIssuePanels(?array $preferredCollapsedIssuePanels): self
    {
        if (null === $preferredCollapsedIssuePanels) {
            $this->preferredCollapsedIssuePanels = null;

            return $this;
        }

        $this->preferredCollapsedIssuePanels = IssuePanelIds::sanitize($preferredCollapsedIssuePanels);

        return $this;
    }

    public function getProductTourSeenAt(): ?DateTimeImmutable
    {
        return $this->productTourSeenAt;
    }

    public function isProductTourSeen(): bool
    {
        return $this->productTourSeenAt instanceof DateTimeImmutable;
    }

    public function markProductTourSeen(?DateTimeImmutable $at = null): self
    {
        $this->productTourSeenAt = $at ?? new DateTimeImmutable();
        $pages = [];
        foreach (ProductTourPage::all() as $page) {
            $pages[] = $page->value;
        }
        $this->productTourSeenPages = $pages;

        return $this;
    }

    public function clearProductTourSeen(): self
    {
        $this->productTourSeenAt = null;
        $this->productTourSeenPages = null;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProductTourSeenPages(): array
    {
        if (null === $this->productTourSeenPages) {
            return [];
        }

        $allowed = array_map(
            static fn (ProductTourPage $page): string => $page->value,
            ProductTourPage::all(),
        );

        return array_values(array_unique(array_filter(
            $this->productTourSeenPages,
            static fn (mixed $id): bool => \is_string($id) && \in_array($id, $allowed, true),
        )));
    }

    public function hasSeenTourPage(string $page): bool
    {
        if ($this->isProductTourSeen()) {
            return true;
        }

        return \in_array($page, $this->getProductTourSeenPages(), true);
    }

    public function markTourPageSeen(string $page): self
    {
        $allowed = array_map(
            static fn (ProductTourPage $p): string => $p->value,
            ProductTourPage::all(),
        );
        if (!\in_array($page, $allowed, true)) {
            return $this;
        }

        $pages = $this->getProductTourSeenPages();
        if (!\in_array($page, $pages, true)) {
            $pages[] = $page;
        }
        $this->productTourSeenPages = $pages;

        if (\count($pages) >= \count($allowed)) {
            $this->productTourSeenAt ??= new DateTimeImmutable();
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEnabledProductTourPages(): array
    {
        $all = array_map(
            static fn (ProductTourPage $page): string => $page->value,
            ProductTourPage::all(),
        );
        if ($this->isProductTourSeen()) {
            return [];
        }

        return array_values(array_diff($all, $this->getProductTourSeenPages()));
    }

    /**
     * @param list<string>|array<int, mixed> $enabledPages
     */
    public function syncEnabledProductTourPages(array $enabledPages): self
    {
        $allowed = array_map(
            static fn (ProductTourPage $page): string => $page->value,
            ProductTourPage::all(),
        );
        $enabled = array_values(array_unique(array_filter(
            $enabledPages,
            static fn (mixed $id): bool => \is_string($id) && \in_array($id, $allowed, true),
        )));
        $completed = array_values(array_diff($allowed, $enabled));

        if ([] === $completed) {
            $this->productTourSeenAt = null;
            $this->productTourSeenPages = null;

            return $this;
        }

        $this->productTourSeenPages = $completed;
        $this->productTourSeenAt = \count($completed) >= \count($allowed) ? new DateTimeImmutable() : null;

        return $this;
    }

    public function isPushNotificationsEnabled(): bool
    {
        return $this->pushNotificationsEnabled;
    }

    public function setPushNotificationsEnabled(bool $pushNotificationsEnabled): self
    {
        $this->pushNotificationsEnabled = $pushNotificationsEnabled;

        return $this;
    }

    /**
     * Clear display prefs for GDPR anonymize (locale/theme/motion/contrast + push + tours/panels).
     */
    public function resetForAnonymize(): self
    {
        $this->preferredLocale = null;
        $this->preferredTheme = null;
        $this->preferredContentWidth = null;
        $this->preferredUiDensity = null;
        $this->preferredMotion = null;
        $this->preferredFontScale = null;
        $this->preferredContrast = null;
        $this->preferredSidebar = null;
        $this->preferredCollapsedIssuePanels = null;
        $this->productTourSeenAt = null;
        $this->productTourSeenPages = null;
        $this->pushNotificationsEnabled = false;

        return $this;
    }
}
