<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Project\Repository\ProjectRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Telemetry project owning API keys, memberships, and ingested data.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'uniq_project_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_project_uuid', columns: ['uuid'])]
class Project implements AuditableInterface
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

    /** Per-project retention days; null inherits `beacon.retention_days`. */
    #[ORM\Column(nullable: true)]
    private ?int $retentionDays = null;

    /** Per-project max stored events; null inherits `beacon.retention_max_events`. */
    #[ORM\Column(nullable: true)]
    private ?int $retentionMaxEvents = null;

    /** Per-project ingest rate limit (per minute); null inherits `beacon.ingest_rate_limit`. */
    #[ORM\Column(nullable: true)]
    private ?int $ingestRateLimitPerMinute = null;

    /** Daily event quota; null inherits `beacon.event_quota_daily`. */
    #[ORM\Column(nullable: true)]
    private ?int $eventQuotaDaily = null;

    /** When false, Envelope ingest is rejected (admin suspend / governance). */
    #[ORM\Column(options: ['default' => true])]
    private bool $ingestEnabled = true;

    /** @var Collection<int, ProjectApiKey> */
    #[ORM\OneToMany(targetEntity: ProjectApiKey::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    private Collection $apiKeys;

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, ProjectGroupAccess> */
    #[ORM\OneToMany(targetEntity: ProjectGroupAccess::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    private Collection $groupAccesses;

    /** @var Collection<int, Issue> */
    #[ORM\OneToMany(targetEntity: Issue::class, mappedBy: 'project')]
    private Collection $issues;

    /** @var Collection<int, NotificationDestination> */
    #[ORM\OneToMany(targetEntity: NotificationDestination::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    private Collection $notificationDestinations;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->ensureUuid();
        $this->apiKeys = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->groupAccesses = new ArrayCollection();
        $this->issues = new ArrayCollection();
        $this->notificationDestinations = new ArrayCollection();
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
        $this->description = $description;

        return $this;
    }

    public function getRetentionDays(): ?int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(?int $retentionDays): self
    {
        $this->retentionDays = $retentionDays;

        return $this;
    }

    public function getRetentionMaxEvents(): ?int
    {
        return $this->retentionMaxEvents;
    }

    public function setRetentionMaxEvents(?int $retentionMaxEvents): self
    {
        $this->retentionMaxEvents = $retentionMaxEvents;

        return $this;
    }

    public function getIngestRateLimitPerMinute(): ?int
    {
        return $this->ingestRateLimitPerMinute;
    }

    public function setIngestRateLimitPerMinute(?int $ingestRateLimitPerMinute): self
    {
        $this->ingestRateLimitPerMinute = $ingestRateLimitPerMinute;

        return $this;
    }

    public function getEventQuotaDaily(): ?int
    {
        return $this->eventQuotaDaily;
    }

    public function setEventQuotaDaily(?int $eventQuotaDaily): self
    {
        $this->eventQuotaDaily = $eventQuotaDaily;

        return $this;
    }

    public function isIngestEnabled(): bool
    {
        return $this->ingestEnabled;
    }

    public function setIngestEnabled(bool $ingestEnabled): self
    {
        $this->ingestEnabled = $ingestEnabled;

        return $this;
    }

    /**
     * @return Collection<int, ProjectApiKey>
     */
    public function getApiKeys(): Collection
    {
        return $this->apiKeys;
    }

    public function addApiKey(ProjectApiKey $apiKey): self
    {
        if (!$this->apiKeys->contains($apiKey)) {
            $this->apiKeys->add($apiKey);
            $apiKey->setProject($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(ProjectMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setProject($this);
        }

        return $this;
    }

    public function removeMembership(ProjectMembership $membership): self
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    /**
     * @return Collection<int, ProjectGroupAccess>
     */
    public function getGroupAccesses(): Collection
    {
        return $this->groupAccesses;
    }

    public function addGroupAccess(ProjectGroupAccess $access): self
    {
        if (!$this->groupAccesses->contains($access)) {
            $this->groupAccesses->add($access);
            $access->setProject($this);
        }

        return $this;
    }

    public function removeGroupAccess(ProjectGroupAccess $access): self
    {
        $this->groupAccesses->removeElement($access);

        return $this;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    /**
     * @return Collection<int, NotificationDestination>
     */
    public function getNotificationDestinations(): Collection
    {
        return $this->notificationDestinations;
    }

    public function addNotificationDestination(NotificationDestination $destination): self
    {
        if (!$this->notificationDestinations->contains($destination)) {
            $this->notificationDestinations->add($destination);
            $destination->setProject($this);
        }

        return $this;
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
