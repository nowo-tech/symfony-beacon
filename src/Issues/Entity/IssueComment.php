<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueCommentRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plain-text discussion comment on an issue.
 */
#[ORM\Entity(repositoryClass: IssueCommentRepository::class)]
#[ORM\Table(name: 'issue_comment')]
#[ORM\UniqueConstraint(name: 'uniq_issue_comment_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_issue_comment_issue_created', columns: ['issue_id', 'created_at'])]
class IssueComment
{
    use PublicUuidTrait;

    public const int BODY_MAX_LENGTH = 5000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->ensureUuid();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): self
    {
        $this->issue = $issue;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
