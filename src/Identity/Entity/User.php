<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserRepository;
use App\Identity\Tour\ProductTourPage;
use App\Issues\IssuePanelIds;
use App\Project\Entity\ProjectMembership;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Nowo\UserKitBundle\Model\AccountStatusInterface;
use Nowo\UserKitBundle\Model\EnabledUserTrait;
use Nowo\UserKitBundle\Model\LastActivityInterface;
use Nowo\UserKitBundle\Model\LastActivityTrait;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application user (AuthKit / Security / UserKit) with locale, theme, and issue panel preferences.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_app_user_uuid', columns: ['uuid'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, HasPasswordPolicyInterface, AccountStatusInterface, LastActivityInterface, AuditableInterface
{
    use EnabledUserTrait;
    use LastActivityTrait;
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 120)]
    private string $displayName = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    /** Hashed AuthKit password-reset credential (nullable when no active reset). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    /** Expiry for {@see $passwordResetToken}. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $passwordResetExpiresAt = null;

    /**
     * Transient plain password for forms (never persisted).
     * Validated against password history via {@see PasswordPolicy}.
     */
    #[PasswordPolicy]
    private ?string $plainPassword = null;

    /** Last password change; null = never tracked (expiry skipped until first change). */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $passwordChangedAt = null;

    /** @var Collection<int, PasswordHistory> */
    #[ORM\OneToMany(targetEntity: PasswordHistory::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $passwordHistory;

    /** Preferred UI locale (`en` / `es` / `de` / `nl` / `fr` / `it` / `pt`); null = follow request / browser. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredLocale = null;

    /** Preferred color theme (`light` / `dark`); null = follow device / localStorage. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredTheme = null;

    /** Main content width: `content` (centered max-width) or `full`. Null = content. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredContentWidth = null;

    /** UI density: `comfortable` (default) or `compact`. Null = comfortable. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredUiDensity = null;

    /**
     * Motion preference: null = follow OS, `reduce` = minimize motion, `full` = allow motion.
     */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredMotion = null;

    /** Root font scale: `sm` | `md` (default) | `lg`. Null = md. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredFontScale = null;

    /** Contrast: null = system, `more` = stronger ink/borders. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $preferredContrast = null;

    /** Desktop sidebar default: `expanded` (default) or `collapsed`. Null = expanded. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $preferredSidebar = null;

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

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->ensureUuid();
        $this->memberships = new ArrayCollection();
        $this->passwordHistory = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    /**
     * Two-letter initials for the avatar bubble (from display name, else email).
     */
    public function getInitials(): string
    {
        $name = trim($this->displayName);
        if ('' !== $name) {
            $parts = preg_split('/\s+/u', $name) ?: [];
            $parts = array_values(array_filter($parts, static fn (string $p): bool => '' !== $p));
            if (\count($parts) >= 2) {
                return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[\count($parts) - 1], 0, 1));
            }

            return mb_strtoupper(mb_substr($name, 0, 2));
        }

        return mb_strtoupper(mb_substr($this->email, 0, 2));
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetExpiresAt(): ?DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?DateTimeImmutable $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getPasswordChangedAt(): ?DateTime
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(DateTime $dateTime): self
    {
        $this->passwordChangedAt = $dateTime;

        return $this;
    }

    /**
     * @return Collection<int, PasswordHistoryInterface>
     */
    public function getPasswordHistory(): Collection
    {
        /** @var Collection<int, PasswordHistoryInterface> $history */
        $history = $this->passwordHistory;

        return $history;
    }

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if (!$passwordHistory instanceof PasswordHistory) {
            throw new InvalidArgumentException(\sprintf('Expected %s, got %s.', PasswordHistory::class, $passwordHistory::class));
        }

        if (!$this->passwordHistory->contains($passwordHistory)) {
            $this->passwordHistory->add($passwordHistory);
            $passwordHistory->setUser($this);
        }

        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if ($passwordHistory instanceof PasswordHistory && $this->passwordHistory->removeElement($passwordHistory)) {
            // Owning side stays on history; orphanRemoval cleans up on flush.
        }

        return $this;
    }

    public function getPreferredLocale(): ?string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(?string $preferredLocale): self
    {
        $normalized = null !== $preferredLocale ? strtolower(trim($preferredLocale)) : null;
        $this->preferredLocale = '' !== $normalized ? $normalized : null;

        return $this;
    }

    public function getPreferredTheme(): ?string
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
        return 'full' === $this->preferredContentWidth ? 'full' : 'content';
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
        return 'compact' === $this->preferredUiDensity ? 'compact' : 'comfortable';
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

    public function getPreferredMotion(): ?string
    {
        return $this->preferredMotion;
    }

    public function setPreferredMotion(?string $preferredMotion): self
    {
        $normalized = null !== $preferredMotion ? strtolower(trim($preferredMotion)) : null;
        if (null !== $normalized && !\in_array($normalized, ['reduce', 'full'], true)) {
            $normalized = null;
        }
        $this->preferredMotion = $normalized;

        return $this;
    }

    public function getPreferredFontScale(): string
    {
        return \in_array($this->preferredFontScale, ['sm', 'lg'], true) ? $this->preferredFontScale : 'md';
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

    public function getPreferredContrast(): ?string
    {
        return $this->preferredContrast;
    }

    public function setPreferredContrast(?string $preferredContrast): self
    {
        $normalized = null !== $preferredContrast ? strtolower(trim($preferredContrast)) : null;
        if (null !== $normalized && 'more' !== $normalized) {
            $normalized = null;
        }
        $this->preferredContrast = $normalized;

        return $this;
    }

    public function getPreferredSidebar(): string
    {
        return 'collapsed' === $this->preferredSidebar ? 'collapsed' : 'expanded';
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
     * Tours the user still wants to allow (auto-start until completed once).
     *
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
     * Persist which tours remain enabled; unselected pages are treated as completed/hidden.
     *
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

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /**
     * @return Collection<int, ProjectMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?object $createdBy): void
    {
        $this->createdBy = $createdBy instanceof self ? $createdBy : null;
    }

    public function getUpdatedBy(): ?object
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?object $updatedBy): void
    {
        $this->updatedBy = $updatedBy instanceof self ? $updatedBy : null;
    }
}
