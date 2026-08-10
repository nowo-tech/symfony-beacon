<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\InstanceRoleRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Named instance Security role that bundles {@see InstancePermission} capabilities.
 *
 * {@see $code} is exposed via {@see User::getRoles()} (e.g. ROLE_SUPPORT).
 */
#[ORM\Entity(repositoryClass: InstanceRoleRepository::class)]
#[ORM\Table(name: 'role')]
#[ORM\UniqueConstraint(name: 'uniq_role_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'uniq_role_uuid', columns: ['uuid'])]
class InstanceRole implements AuditableInterface
{
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    /** Symfony ROLE_* string merged into the user token. */
    #[ORM\Column(length: 60)]
    private string $code = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $enabled = true;

    /** Built-in roles (e.g. Administrator) cannot be deleted; code stays fixed. */
    #[ORM\Column(name: 'is_system')]
    private bool $system = false;

    /** @var Collection<int, InstancePermission> */
    #[ORM\ManyToMany(targetEntity: InstancePermission::class, inversedBy: 'roles')]
    #[ORM\JoinTable(name: 'role_permission')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['category' => 'ASC', 'name' => 'ASC'])]
    private Collection $permissions;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'instanceRoles')]
    #[ORM\OrderBy(['displayName' => 'ASC'])]
    private Collection $users;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->ensureUuid();
        $this->permissions = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $normalized = strtoupper(trim($code));
        if (!str_starts_with($normalized, 'ROLE_')) {
            $normalized = 'ROLE_'.$normalized;
        }
        $this->code = $normalized;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

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
     * @return Collection<int, InstancePermission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(InstancePermission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(InstancePermission $permission): self
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    public function clearPermissions(): self
    {
        $this->permissions->clear();

        return $this;
    }

    public function hasPermissionKey(string $key): bool
    {
        $needle = strtolower(trim($key));
        foreach ($this->permissions as $permission) {
            if ($permission->getKey() === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
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
