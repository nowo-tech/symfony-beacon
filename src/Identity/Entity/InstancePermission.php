<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\InstancePermissionRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Instance-level capability key used with Security {@see isGranted()} (e.g. project.view).
 *
 * Distinct from project membership roles ({@see \App\Project\Enum\ProjectRole}).
 * Locale labels live in {@see InstancePermissionTranslation} (not JSON columns).
 * Administration surfaces stay gated by ROLE_ADMIN — not catalogued here.
 */
#[ORM\Entity(repositoryClass: InstancePermissionRepository::class)]
#[ORM\Table(name: 'permission')]
#[ORM\UniqueConstraint(name: 'uniq_permission_key', columns: ['permission_key'])]
#[ORM\UniqueConstraint(name: 'uniq_permission_uuid', columns: ['uuid'])]
class InstancePermission implements AuditableInterface
{
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Stable Security attribute (dot notation). */
    #[ORM\Column(name: 'permission_key', length: 120)]
    private string $key = '';

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Grouping label for admin UI (e.g. access, instance, observability). */
    #[ORM\Column(length: 60)]
    private string $category = 'general';

    /** Seeded catalog rows cannot be deleted; key stays immutable. */
    #[ORM\Column(name: 'is_system')]
    private bool $system = false;

    /** @var Collection<int, InstanceRole> */
    #[ORM\ManyToMany(targetEntity: InstanceRole::class, mappedBy: 'permissions')]
    private Collection $roles;

    /** @var Collection<int, InstancePermissionTranslation> */
    #[ORM\OneToMany(targetEntity: InstancePermissionTranslation::class, mappedBy: 'permission', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->ensureUuid();
        $this->roles = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = strtolower(trim($key));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $trimmed = null !== $description ? trim($description) : null;
        $this->description = (null === $trimmed || '' === $trimmed) ? null : $trimmed;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = strtolower(trim($category));

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function setSystem(bool $system): self
    {
        $this->system = $system;

        return $this;
    }

    /**
     * @return Collection<int, InstanceRole>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * @return Collection<int, InstancePermissionTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function findTranslation(string $locale): ?InstancePermissionTranslation
    {
        $locale = strtolower(trim($locale));
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(InstancePermissionTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPermission($this);
        }

        return $this;
    }

    public function removeTranslation(InstancePermissionTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getPermission() === $this) {
            $translation->setPermission(null);
        }

        return $this;
    }

    public function getNameForLocale(string $locale): ?string
    {
        $translation = $this->findTranslation($locale);
        if (!$translation instanceof InstancePermissionTranslation) {
            return null;
        }
        $name = trim($translation->getName());

        return '' === $name ? null : $name;
    }

    public function getDescriptionForLocale(string $locale): ?string
    {
        $translation = $this->findTranslation($locale);
        if (!$translation instanceof InstancePermissionTranslation) {
            return null;
        }

        return $translation->getDescription();
    }

    /**
     * Replace Translatable rows from locale → name/description maps.
     * Locales with empty name and description are omitted (row removed).
     *
     * @param array<string, string> $names
     * @param array<string, string> $descriptions
     */
    public function syncTranslations(array $names, array $descriptions): self
    {
        $keepLocales = [];
        foreach (array_unique([...array_keys($names), ...array_keys($descriptions)]) as $locale) {
            if (!\is_string($locale)) {
                continue;
            }
            $locale = strtolower(trim($locale));
            $name = trim((string) ($names[$locale] ?? ''));
            $description = trim((string) ($descriptions[$locale] ?? ''));
            if ('' === $name && '' === $description) {
                continue;
            }
            $keepLocales[$locale] = true;
            $existing = $this->findTranslation($locale);
            if (!$existing instanceof InstancePermissionTranslation) {
                $existing = new InstancePermissionTranslation()->setLocale($locale);
                $this->addTranslation($existing);
            }
            $existing->setName($name);
            $existing->setDescription('' === $description ? null : $description);
        }

        foreach ($this->translations->toArray() as $translation) {
            if (!isset($keepLocales[$translation->getLocale()])) {
                $this->removeTranslation($translation);
            }
        }

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
