<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Entity\Issue;

/**
 * Shared Issue → JSON array shape for Read API and project export.
 */
final class IssueJsonNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(Issue $issue): array
    {
        return [
            'uuid' => $issue->getUuid(),
            'title' => $issue->getTitle(),
            'level' => $issue->getLevel(),
            'status' => $issue->getStatus()->value,
            'priority' => $issue->getPriority()->value,
            'culprit' => $issue->getCulprit(),
            'event_count' => $issue->getEventCount(),
            'first_seen' => $issue->getFirstSeen()->format(\DATE_ATOM),
            'last_seen' => $issue->getLastSeen()->format(\DATE_ATOM),
            'first_release' => $issue->getFirstRelease(),
            'last_release' => $issue->getLastRelease(),
            'last_environment' => $issue->getLastEnvironment(),
            'assignee_email' => $issue->getAssignee()?->getEmail(),
            'duplicate_of_uuid' => $issue->getDuplicateOf()?->getUuid(),
        ];
    }
}
