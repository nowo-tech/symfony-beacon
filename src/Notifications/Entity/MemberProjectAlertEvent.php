<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-project override of a member alert event (opt-out).
 * Missing row inherits the account-level setting for that event.
 */
#[ORM\Entity(repositoryClass: MemberProjectAlertEventRepository::class)]
#[ORM\Table(name: 'member_project_alert_event')]
#[ORM\UniqueConstraint(name: 'uniq_member_project_alert_event', columns: ['user_id', 'project_id', 'event'])]
#[ORM\Index(name: 'idx_member_project_alert_event_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_member_project_alert_event_project', columns: ['project_id'])]
class MemberProjectAlertEvent
{
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

    #[ORM\Column(length: 32, enumType: MemberAlertEvent::class)]
    private MemberAlertEvent $event = MemberAlertEvent::IssueNew;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(length: 16, enumType: MemberAlertScope::class, options: ['default' => 'all'])]
    private MemberAlertScope $scope = MemberAlertScope::All;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getEvent(): MemberAlertEvent
    {
        return $this->event;
    }

    public function setEvent(MemberAlertEvent $event): self
    {
        $this->event = $event;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getScope(): MemberAlertScope
    {
        return $this->scope;
    }

    public function setScope(MemberAlertScope $scope): self
    {
        $this->scope = $scope;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }
}
