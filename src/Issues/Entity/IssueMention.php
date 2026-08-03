<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueMentionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted @mention of a user inside an issue comment (dashboard Mentions inbox).
 */
#[ORM\Entity(repositoryClass: IssueMentionRepository::class)]
#[ORM\Table(name: 'issue_mention')]
#[ORM\Index(name: 'idx_issue_mention_user_created', columns: ['mentioned_user_id', 'created_at'])]
#[ORM\Index(name: 'idx_issue_mention_user_unread', columns: ['mentioned_user_id', 'read_at'])]
#[ORM\UniqueConstraint(name: 'uniq_issue_mention_comment_user', columns: ['comment_id', 'mentioned_user_id'])]
class IssueMention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?IssueComment $comment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $mentionedUser = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComment(): ?IssueComment
    {
        return $this->comment;
    }

    public function setComment(?IssueComment $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getMentionedUser(): ?User
    {
        return $this->mentionedUser;
    }

    public function setMentionedUser(?User $mentionedUser): self
    {
        $this->mentionedUser = $mentionedUser;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(?DateTimeImmutable $at = null): self
    {
        $this->readAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function isUnread(): bool
    {
        return null === $this->readAt;
    }
}
