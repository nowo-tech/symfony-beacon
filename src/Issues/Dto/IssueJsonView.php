<?php

declare(strict_types=1);

namespace App\Issues\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Serializer-friendly Issue JSON view (snake_case wire names).
 */
final readonly class IssueJsonView
{
    public function __construct(
        public string $uuid,
        public string $title,
        public string $level,
        public string $status,
        public string $priority,
        public ?string $culprit,
        #[SerializedName('event_count')]
        public int $eventCount,
        #[SerializedName('first_seen')]
        public string $firstSeen,
        #[SerializedName('last_seen')]
        public string $lastSeen,
        #[SerializedName('first_release')]
        public ?string $firstRelease,
        #[SerializedName('last_release')]
        public ?string $lastRelease,
        #[SerializedName('last_environment')]
        public ?string $lastEnvironment,
        #[SerializedName('assignee_email')]
        public ?string $assigneeEmail,
        #[SerializedName('duplicate_of_uuid')]
        public ?string $duplicateOfUuid,
    ) {
    }
}
