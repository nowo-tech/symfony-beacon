<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Entity\Project;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Per-project outbound notification channel (Slack, Discord, Teams, Telegram, email, or HTTP).
 */
#[ORM\Entity(repositoryClass: NotificationDestinationRepository::class)]
#[ORM\Table(name: 'notification_destination')]
#[ORM\UniqueConstraint(name: 'uniq_notification_destination_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_notif_dest_project_enabled', columns: ['project_id', 'enabled'])]
class NotificationDestination implements AuditableInterface
{
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notificationDestinations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    public function __construct()
    {
        $this->ensureUuid();
    }

    #[ORM\Column(length: 120)]
    private string $label = '';

    #[ORM\Column(length: 20, enumType: NotificationDestinationType::class)]
    private NotificationDestinationType $type = NotificationDestinationType::Slack;

    /** Webhook URL (may contain tokens; encrypted at rest). */
    #[ORM\Column(type: 'text')]
    #[Encrypted]
    private string $endpointUrl = '';

    #[ORM\Column]
    private bool $enabled = true;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $categories = ['error', 'warning', 'n_plus_one'];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);
        $this->touch();

        return $this;
    }

    public function getType(): NotificationDestinationType
    {
        return $this->type;
    }

    public function setType(NotificationDestinationType $type): self
    {
        $this->type = $type;
        $this->touch();

        return $this;
    }

    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    public function setEndpointUrl(string $endpointUrl): self
    {
        $this->endpointUrl = trim($endpointUrl);
        $this->touch();

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param list<string> $categories
     */
    public function setCategories(array $categories): self
    {
        $this->categories = NotificationCategories::sanitize($categories);
        $this->touch();

        return $this;
    }

    public function matchesCategory(string $category): bool
    {
        return \in_array($category, $this->categories, true);
    }

    public function maskedEndpointUrl(): string
    {
        $value = $this->endpointUrl;
        if (NotificationDestinationType::Email === $this->type) {
            $at = strpos($value, '@');
            if (false === $at) {
                return '••••';
            }

            $local = substr($value, 0, $at);
            $domain = substr($value, $at);
            $prefix = substr($local, 0, min(2, \strlen($local)));

            return $prefix.str_repeat('•', max(2, \strlen($local) - \strlen($prefix))).$domain;
        }

        if (NotificationDestinationType::Telegram === $this->type) {
            $at = strrpos($value, '@');
            if (false === $at) {
                return '••••@••••';
            }

            return '••••…••••@'.substr($value, $at + 1);
        }

        if (\strlen($value) <= 16) {
            return str_repeat('•', max(4, \strlen($value)));
        }

        return substr($value, 0, 12).'…'.substr($value, -8);
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

    private function touch(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());
    }
}
