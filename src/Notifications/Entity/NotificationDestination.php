<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-project outbound notification channel (Slack or HTTP webhook).
 */
#[ORM\Entity(repositoryClass: NotificationDestinationRepository::class)]
#[ORM\Table(name: 'notification_destination')]
#[ORM\Index(name: 'idx_notif_dest_project_enabled', columns: ['project_id', 'enabled'])]
class NotificationDestination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notificationDestinations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 120)]
    private string $label = '';

    #[ORM\Column(length: 20, enumType: NotificationDestinationType::class)]
    private NotificationDestinationType $type = NotificationDestinationType::Slack;

    #[ORM\Column(length: 2048)]
    private string $endpointUrl = '';

    #[ORM\Column]
    private bool $enabled = true;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $categories = ['error', 'warning', 'n_plus_one'];

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
        $url = $this->endpointUrl;
        if (\strlen($url) <= 16) {
            return str_repeat('•', max(4, \strlen($url)));
        }

        return substr($url, 0, 12).'…'.substr($url, -8);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
