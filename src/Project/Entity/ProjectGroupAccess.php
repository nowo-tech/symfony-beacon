<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Identity\Entity\UserGroup;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Grants every member of a user group a project role (admin, member, or viewer; owners stay direct).
 */
#[ORM\Entity(repositoryClass: ProjectGroupAccessRepository::class)]
#[ORM\Table(name: 'project_group_access')]
#[ORM\UniqueConstraint(name: 'uniq_project_group_access', columns: ['project_id', 'user_group_id'])]
#[ORM\UniqueConstraint(name: 'uniq_project_group_access_uuid', columns: ['uuid'])]
class ProjectGroupAccess
{
    use PublicUuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'groupAccesses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserGroup $userGroup = null;

    #[ORM\Column(length: 20, enumType: ProjectRole::class)]
    private ProjectRole $role = ProjectRole::Member;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->ensureUuid();
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

    public function getUserGroup(): ?UserGroup
    {
        return $this->userGroup;
    }

    public function setUserGroup(?UserGroup $userGroup): self
    {
        $this->userGroup = $userGroup;

        return $this;
    }

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function setRole(ProjectRole $role): self
    {
        if (ProjectRole::Owner === $role) {
            throw new InvalidArgumentException('Groups cannot be assigned the owner role.');
        }
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
