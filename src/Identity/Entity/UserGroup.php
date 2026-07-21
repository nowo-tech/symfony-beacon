<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserGroupRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Named user group for bulk project access.
 */
#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\Table(name: 'user_group')]
#[ORM\UniqueConstraint(name: 'uniq_user_group_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_user_group_uuid', columns: ['uuid'])]
class UserGroup
{
    use PublicUuidTrait;

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

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, UserGroupMembership> */
    #[ORM\OneToMany(targetEntity: UserGroupMembership::class, mappedBy: 'userGroup', cascade: ['persist'], orphanRemoval: true)]
    private Collection $memberships;

    public function __construct()
    {
        $this->ensureUuid();
        $this->createdAt = new DateTimeImmutable();
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
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
}
