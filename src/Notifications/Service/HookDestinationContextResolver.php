<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Entity\Project;

/**
 * Resolves hook destinations by UUID, expected channel type, and signing secret presence.
 */
final readonly class HookDestinationContextResolver
{
    public function __construct(
        private NotificationDestinationRepository $destinationRepository,
    ) {
    }

    public function resolve(string $destinationUuid, NotificationDestinationType $expectedType): ?HookDestinationContext
    {
        if ('' === $destinationUuid) {
            return null;
        }

        $destination = $this->destinationRepository->findOneBy(['uuid' => $destinationUuid]);
        if (!$destination instanceof NotificationDestination
            || $expectedType !== $destination->getType()
            || !$destination->hasSigningSecret()
        ) {
            return null;
        }

        $project = $destination->getProject();
        if (!$project instanceof Project) {
            return null;
        }

        return new HookDestinationContext($destination, $project, (string) $destination->getSigningSecret());
    }

    public function resolveForProject(
        string $destinationUuid,
        NotificationDestinationType $expectedType,
        string $projectUuid,
    ): ?HookDestinationContext {
        $context = $this->resolve($destinationUuid, $expectedType);
        if (!$context instanceof HookDestinationContext || $context->project->getUuid() !== $projectUuid) {
            return null;
        }

        return $context;
    }
}
