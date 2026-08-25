<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

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
    use TimestampableTrait;

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

    public function getEvent(): MemberAlertEvent
    {
        return $this->event;
    }

    public function setEvent(MemberAlertEvent $event): self
    {
        $this->event = $event;
        $this->setUpdatedAt(new DateTimeImmutable());

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

    public function getScope(): MemberAlertScope
    {
        return $this->scope;
    }

    public function setScope(MemberAlertScope $scope): self
    {
        $this->scope = $scope;
        $this->setUpdatedAt(new DateTimeImmutable());

        return $this;
    }
}
