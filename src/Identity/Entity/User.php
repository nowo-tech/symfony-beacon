<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserRepository;
use App\Issues\IssuePanelIds;
use App\Project\Entity\ProjectMembership;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Nowo\AuditKitBundle\Model\TimestampableInterface;
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
class User implements UserInterface, PasswordAuthenticatedUserInterface, HasPasswordPolicyInterface, AccountStatusInterface, LastActivityInterface, TimestampableInterface
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

    /**
     * Issue/event panel ids that should start collapsed (browser can override via localStorage).
     *
     * @var list<string>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $preferredCollapsedIssuePanels = null;

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $memberships;

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
}
