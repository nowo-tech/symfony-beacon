<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserGroupRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Named user group for bulk project access.
 */
#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\Table(name: 'user_group')]
#[ORM\UniqueConstraint(name: 'uniq_user_group_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_user_group_uuid', columns: ['uuid'])]
class UserGroup implements AuditableInterface
{
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 120)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, UserGroupMembership> */
    #[ORM\OneToMany(targetEntity: UserGroupMembership::class, mappedBy: 'userGroup', cascade: ['persist'], orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->ensureUuid();
        $this->memberships = new ArrayCollection();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = strtolower(trim($slug));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;

        return $this;
    }

    /**
     * @return Collection<int, UserGroupMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(UserGroupMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setUserGroup($this);
        }

        return $this;
    }

    public function removeMembership(UserGroupMembership $membership): self
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    public function hasUser(User $user): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
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
