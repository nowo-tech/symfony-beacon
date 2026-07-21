<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserGroupMembershipRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a user to a user group.
 */
#[ORM\Entity(repositoryClass: UserGroupMembershipRepository::class)]
#[ORM\Table(name: 'user_group_membership')]
#[ORM\UniqueConstraint(name: 'uniq_user_group_membership', columns: ['user_group_id', 'user_id'])]
class UserGroupMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserGroup $userGroup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserGroup(): ?UserGroup
    {
        return $this->userGroup;
    }

    public function setUserGroup(?UserGroup $userGroup): self
    {
        $this->userGroup = $userGroup;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
