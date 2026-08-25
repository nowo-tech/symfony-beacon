<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Per-user per-project member alert master switch (opt-out).
 * Missing row means the project is enabled; event rows live in {@see MemberProjectAlertEvent}.
 */
#[ORM\Entity(repositoryClass: MemberProjectAlertPreferenceRepository::class)]
#[ORM\Table(name: 'member_project_alert_preference')]
#[ORM\UniqueConstraint(name: 'uniq_member_project_alert_user_project', columns: ['user_id', 'project_id'])]
#[ORM\Index(name: 'idx_member_project_alert_user', columns: ['user_id'])]
class MemberProjectAlertPreference
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->setCreatedAt($now);
        $this->setUpdatedAt($now);
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->setUpdatedAt(new DateTimeImmutable());

        return $this;
    }
}
