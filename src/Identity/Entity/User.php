<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\Repository\UserRepository;
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
 * Application user (AuthKit / Security / UserKit).
 *
 * Display / tour / push preferences live in {@see UserUiPreferences} (embedded columns).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_app_user_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_app_user_slack_user_id', columns: ['slack_user_id'])]
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

    /**
     * Slack member user id (e.g. U012ABCDEF) for interactive Assign / Resolve attribution.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $slackUserId = null;

    /**
     * E.164-ish phone for AuthKit QR login approval ({@see phoneVerifiedAt}).
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    /** When set, AuthKit QR login treats {@see $phone} as verified. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $phoneVerifiedAt = null;

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

    /**
     * UI preferences (locale, theme, density, tours, push). Same DB columns as before (`columnPrefix: false`).
     */
    #[ORM\Embedded(class: UserUiPreferences::class, columnPrefix: false)]
    private UserUiPreferences $uiPreferences;

    /** When set, personal identifiers were scrubbed (GDPR anonymize); login remains disabled. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $anonymizedAt = null;

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $memberships;

    /**
     * Assignable instance RBAC roles (permission bundles). Codes merge into {@see getRoles()}.
     *
     * @var Collection<int, InstanceRole>
     */
    #[ORM\ManyToMany(targetEntity: InstanceRole::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'role_user')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $instanceRoles;

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
        $this->instanceRoles = new ArrayCollection();
        $this->passwordHistory = new ArrayCollection();
        $this->uiPreferences = new UserUiPreferences();
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

    public function getSlackUserId(): ?string
    {
        return $this->slackUserId;
    }

    public function setSlackUserId(?string $slackUserId): self
    {
        if (null === $slackUserId) {
            $this->slackUserId = null;

            return $this;
        }

        $trimmed = trim($slackUserId);
        $this->slackUserId = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        if (null === $phone) {
            $this->phone = null;

            return $this;
        }

        $trimmed = trim($phone);
        $this->phone = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getPhoneVerifiedAt(): ?DateTimeImmutable
    {
        return $this->phoneVerifiedAt;
    }

    public function setPhoneVerifiedAt(?DateTimeImmutable $phoneVerifiedAt): self
    {
        $this->phoneVerifiedAt = $phoneVerifiedAt;

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
        foreach ($this->instanceRoles as $instanceRole) {
            if ($instanceRole->isEnabled()) {
                $roles[] = $instanceRole->getCode();
            }
        }

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

    public function getUiPreferences(): UserUiPreferences
    {
        return $this->uiPreferences;
    }

    public function getPreferredLocale(): string
    {
        return $this->uiPreferences->getPreferredLocale();
    }

    /** Stored column before defaults (PrePersist / legacy null rows). */
    public function getPreferredLocaleRaw(): ?string
    {
        return $this->uiPreferences->getPreferredLocaleRaw();
    }

    public function setPreferredLocale(?string $preferredLocale): self
    {
        $this->uiPreferences->setPreferredLocale($preferredLocale);

        return $this;
    }

    public function getPreferredTheme(): string
    {
        return $this->uiPreferences->getPreferredTheme();
    }

    public function getPreferredThemeRaw(): ?string
    {
        return $this->uiPreferences->getPreferredThemeRaw();
    }

    public function setPreferredTheme(?string $preferredTheme): self
    {
        $this->uiPreferences->setPreferredTheme($preferredTheme);

        return $this;
    }

    public function getPreferredContentWidth(): string
    {
        return $this->uiPreferences->getPreferredContentWidth();
    }

    public function getPreferredContentWidthRaw(): ?string
    {
        return $this->uiPreferences->getPreferredContentWidthRaw();
    }

    public function setPreferredContentWidth(?string $preferredContentWidth): self
    {
        $this->uiPreferences->setPreferredContentWidth($preferredContentWidth);

        return $this;
    }

    public function getPreferredUiDensity(): string
    {
        return $this->uiPreferences->getPreferredUiDensity();
    }

    public function getPreferredUiDensityRaw(): ?string
    {
        return $this->uiPreferences->getPreferredUiDensityRaw();
    }

    public function setPreferredUiDensity(?string $preferredUiDensity): self
    {
        $this->uiPreferences->setPreferredUiDensity($preferredUiDensity);

        return $this;
    }

    public function getPreferredMotion(): string
    {
        return $this->uiPreferences->getPreferredMotion();
    }

    public function getPreferredMotionRaw(): ?string
    {
        return $this->uiPreferences->getPreferredMotionRaw();
    }

    public function setPreferredMotion(?string $preferredMotion): self
    {
        $this->uiPreferences->setPreferredMotion($preferredMotion);

        return $this;
    }

    public function getPreferredFontScale(): string
    {
        return $this->uiPreferences->getPreferredFontScale();
    }

    public function getPreferredFontScaleRaw(): ?string
    {
        return $this->uiPreferences->getPreferredFontScaleRaw();
    }

    public function setPreferredFontScale(?string $preferredFontScale): self
    {
        $this->uiPreferences->setPreferredFontScale($preferredFontScale);

        return $this;
    }

    public function getPreferredContrast(): string
    {
        return $this->uiPreferences->getPreferredContrast();
    }

    public function getPreferredContrastRaw(): ?string
    {
        return $this->uiPreferences->getPreferredContrastRaw();
    }

    public function setPreferredContrast(?string $preferredContrast): self
    {
        $this->uiPreferences->setPreferredContrast($preferredContrast);

        return $this;
    }

    public function getPreferredSidebar(): string
    {
        return $this->uiPreferences->getPreferredSidebar();
    }

    public function getPreferredSidebarRaw(): ?string
    {
        return $this->uiPreferences->getPreferredSidebarRaw();
    }

    public function setPreferredSidebar(?string $preferredSidebar): self
    {
        $this->uiPreferences->setPreferredSidebar($preferredSidebar);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPreferredCollapsedIssuePanels(): array
    {
        return $this->uiPreferences->getPreferredCollapsedIssuePanels();
    }

    /**
     * @param list<string>|null $preferredCollapsedIssuePanels
     */
    public function setPreferredCollapsedIssuePanels(?array $preferredCollapsedIssuePanels): self
    {
        $this->uiPreferences->setPreferredCollapsedIssuePanels($preferredCollapsedIssuePanels);

        return $this;
    }

    public function getProductTourSeenAt(): ?DateTimeImmutable
    {
        return $this->uiPreferences->getProductTourSeenAt();
    }

    public function isProductTourSeen(): bool
    {
        return $this->uiPreferences->isProductTourSeen();
    }

    public function markProductTourSeen(?DateTimeImmutable $at = null): self
    {
        $this->uiPreferences->markProductTourSeen($at);

        return $this;
    }

    public function clearProductTourSeen(): self
    {
        $this->uiPreferences->clearProductTourSeen();

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProductTourSeenPages(): array
    {
        return $this->uiPreferences->getProductTourSeenPages();
    }

    public function hasSeenTourPage(string $page): bool
    {
        return $this->uiPreferences->hasSeenTourPage($page);
    }

    public function markTourPageSeen(string $page): self
    {
        $this->uiPreferences->markTourPageSeen($page);

        return $this;
    }

    /**
     * Tours the user still wants to allow (auto-start until completed once).
     *
     * @return list<string>
     */
    public function getEnabledProductTourPages(): array
    {
        return $this->uiPreferences->getEnabledProductTourPages();
    }

    /**
     * Persist which tours remain enabled; unselected pages are treated as completed/hidden.
     *
     * @param list<string>|array<int, mixed> $enabledPages
     */
    public function syncEnabledProductTourPages(array $enabledPages): self
    {
        $this->uiPreferences->syncEnabledProductTourPages($enabledPages);

        return $this;
    }

    public function isPushNotificationsEnabled(): bool
    {
        return $this->uiPreferences->isPushNotificationsEnabled();
    }

    public function setPushNotificationsEnabled(bool $pushNotificationsEnabled): self
    {
        $this->uiPreferences->setPushNotificationsEnabled($pushNotificationsEnabled);

        return $this;
    }

    public function isMemberAlertsEnabled(): bool
    {
        return $this->uiPreferences->isMemberAlertsEnabled();
    }

    public function setMemberAlertsEnabled(bool $memberAlertsEnabled): self
    {
        $this->uiPreferences->setMemberAlertsEnabled($memberAlertsEnabled);

        return $this;
    }

    public function getAnonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt instanceof DateTimeImmutable;
    }

    public function setAnonymizedAt(?DateTimeImmutable $anonymizedAt): self
    {
        $this->anonymizedAt = $anonymizedAt;

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

    /**
     * @return Collection<int, InstanceRole>
     */
    public function getInstanceRoles(): Collection
    {
        return $this->instanceRoles;
    }

    public function addInstanceRole(InstanceRole $role): self
    {
        if (!$this->instanceRoles->contains($role)) {
            $this->instanceRoles->add($role);
            if (!$role->getUsers()->contains($this)) {
                $role->getUsers()->add($this);
            }
        }

        return $this;
    }

    public function removeInstanceRole(InstanceRole $role): self
    {
        if ($this->instanceRoles->removeElement($role)) {
            $role->getUsers()->removeElement($this);
        }

        return $this;
    }

    public function hasInstanceRole(InstanceRole $role): bool
    {
        return $this->instanceRoles->contains($role);
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
