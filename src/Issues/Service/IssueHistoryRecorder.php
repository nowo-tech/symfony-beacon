<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueHistoryEntry;
use App\Issues\IssueHistoryKind;
use App\Shared\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records assignee and status changes on the issue history timeline.
 */
final readonly class IssueHistoryRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recordAssigneeChange(Issue $issue, ?User $from, ?User $to, ?User $actor): void
    {
        $fromId = $from?->getId();
        $toId = $to?->getId();
        if ($fromId === $toId) {
            return;
        }

        $entry = new IssueHistoryEntry();
        $entry->setIssue($issue);
        $entry->setKind(IssueHistoryKind::AssigneeChanged);
        $entry->setActor($actor);
        $entry->setFromAssignee($from);
        $entry->setToAssignee($to);
        $this->entityManager->persist($entry);
        $issue->addHistoryEntry($entry);
    }

    public function recordStatusChange(Issue $issue, IssueStatus $from, IssueStatus $to, ?User $actor): void
    {
        if ($from === $to) {
            return;
        }

        $entry = new IssueHistoryEntry();
        $entry->setIssue($issue);
        $entry->setKind(IssueHistoryKind::StatusChanged);
        $entry->setActor($actor);
        $entry->setFromStatus($from);
        $entry->setToStatus($to);
        $this->entityManager->persist($entry);
        $issue->addHistoryEntry($entry);
    }
}
