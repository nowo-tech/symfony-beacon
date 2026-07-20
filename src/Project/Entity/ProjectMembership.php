<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Identity\Entity\User;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a user to a project with an owner/admin/member role.
 */
#[ORM\Entity(repositoryClass: ProjectMembershipRepository::class)]
#[ORM\Table(name: 'project_membership')]
#[ORM\UniqueConstraint(name: 'uniq_project_user', columns: ['project_id', 'user_id'])]
class ProjectMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: ProjectRole::class)]
    private ProjectRole $role = ProjectRole::Member;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

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

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function setRole(ProjectRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
