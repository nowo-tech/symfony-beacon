<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Issues\Repository\InboundEmailMessageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Deduplicates inbound email webhooks by Message-ID (or provider id).
 */
#[ORM\Entity(repositoryClass: InboundEmailMessageRepository::class)]
#[ORM\Table(name: 'inbound_email_message')]
#[ORM\UniqueConstraint(name: 'uniq_inbound_email_message_id', columns: ['message_id'])]
class InboundEmailMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512)]
    private string $messageId = '';

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $commentUuid = null;

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

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getCommentUuid(): ?string
    {
        return $this->commentUuid;
    }

    public function setCommentUuid(?string $commentUuid): self
    {
        $this->commentUuid = $commentUuid;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
