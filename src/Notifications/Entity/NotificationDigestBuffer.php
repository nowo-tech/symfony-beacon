<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Notifications\Repository\NotificationDigestBufferRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Deferred notification payload held during quiet hours until digest flush.
 */
#[ORM\Entity(repositoryClass: NotificationDigestBufferRepository::class)]
#[ORM\Table(name: 'notification_digest_buffer')]
#[ORM\Index(name: 'idx_notif_digest_buffer_dest_created', columns: ['destination_id', 'created_at'])]
class NotificationDigestBuffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?NotificationDestination $destination = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

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

    public function getDestination(): ?NotificationDestination
    {
        return $this->destination;
    }

    public function setDestination(NotificationDestination $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
