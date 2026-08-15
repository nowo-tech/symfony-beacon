<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Issues\Repository\EventTagRepository;
use App\Project\Entity\Project;
use Doctrine\ORM\Mapping as ORM;

/**
 * Promoted Envelope tag (payload.tags) for indexed issue list filters.
 *
 * Avoids JSON_SEARCH / CAST(payload) scans on the hot path.
 */
#[ORM\Entity(repositoryClass: EventTagRepository::class)]
#[ORM\Table(name: 'event_tag')]
#[ORM\Index(name: 'idx_event_tag_project_key', columns: ['project_id', 'tag_key'])]
#[ORM\Index(name: 'idx_event_tag_project_value', columns: ['project_id', 'tag_value'])]
#[ORM\Index(name: 'idx_event_tag_issue', columns: ['issue_id'])]
#[ORM\UniqueConstraint(name: 'uniq_event_tag_event_key', columns: ['event_id', 'tag_key'])]
class EventTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 120)]
    private string $tagKey = '';

    #[ORM\Column(length: 255)]
    private string $tagValue = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getTagKey(): string
    {
        return $this->tagKey;
    }

    public function setTagKey(string $tagKey): self
    {
        $this->tagKey = $tagKey;

        return $this;
    }

    public function getTagValue(): string
    {
        return $this->tagValue;
    }

    public function setTagValue(string $tagValue): self
    {
        $this->tagValue = $tagValue;

        return $this;
    }
}
