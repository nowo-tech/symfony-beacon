<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Account-level deviation from default member alert settings (opt-out).
 * Missing row for an event means enabled + scope all.
 */
#[ORM\Entity(repositoryClass: MemberAccountAlertEventRepository::class)]
#[ORM\Table(name: 'member_account_alert_event')]
#[ORM\UniqueConstraint(name: 'uniq_member_account_alert_user_event', columns: ['user_id', 'event'])]
#[ORM\Index(name: 'idx_member_account_alert_user', columns: ['user_id'])]
class MemberAccountAlertEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

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
