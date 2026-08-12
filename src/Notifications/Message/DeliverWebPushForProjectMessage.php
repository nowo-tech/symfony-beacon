<?php

declare(strict_types=1);

namespace App\Notifications\Message;

/**
 * Fan-out Web Push for eligible members of a project after a member alert event.
 */
final readonly class DeliverWebPushForProjectMessage
{
    /**
     * @param array<string, mixed> $payload
     * @param list<int>|null       $eligibleUserIds null = all push-enabled project members (legacy); empty = none
     */
    public function __construct(
        public int $projectId,
        public array $payload,
        public ?array $eligibleUserIds = null,
    ) {
    }
}
