<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Project\Entity\Project;

/** Resolved hook destination with project and signing secret. */
final readonly class HookDestinationContext
{
    public function __construct(
        public NotificationDestination $destination,
        public Project $project,
        public string $signingSecret,
    ) {
    }
}
