<?php

declare(strict_types=1);

namespace App\Notifications\Message;

/**
 * Fan-out Web Push for members of a project after a new issue is recorded.
 */
final readonly class DeliverWebPushForProjectMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $projectId,
        public array $payload,
    ) {
    }
}
