<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * One retained delivery attempt for a notification destination.
 */
#[ORM\Entity(repositoryClass: NotificationDeliveryAttemptRepository::class)]
#[ORM\Table(name: 'notification_delivery_attempt')]
#[ORM\Index(name: 'idx_notif_delivery_attempt_destination_time', columns: ['destination_id', 'attempted_at'])]
final class NotificationDeliveryAttempt
{
    private const int MAX_ERROR_SNIPPET_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: NotificationDestination::class, inversedBy: 'deliveryAttempts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?NotificationDestination $destination = null;

    #[ORM\Column]
    private DateTimeImmutable $attemptedAt;

    #[ORM\Column]
    private bool $successful = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorSnippet = null;

    public function __construct()
    {
        $this->attemptedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestination(): ?NotificationDestination
    {
        return $this->destination;
    }

    public function setDestination(?NotificationDestination $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getAttemptedAt(): DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(DateTimeImmutable $attemptedAt): self
    {
        $this->attemptedAt = $attemptedAt;

        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): self
    {
        $this->successful = $successful;
        if ($successful) {
            $this->errorSnippet = null;
        }

        return $this;
    }

    public function getErrorSnippet(): ?string
    {
        return $this->errorSnippet;
    }

    public function setErrorSnippet(?string $errorSnippet): self
    {
        $value = null !== $errorSnippet ? trim($errorSnippet) : null;
        $this->errorSnippet = null !== $value && '' !== $value
            ? mb_substr($value, 0, self::MAX_ERROR_SNIPPET_LENGTH)
            : null;

        return $this;
    }
}
