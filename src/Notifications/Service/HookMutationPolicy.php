<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Policy for interactive notification hooks (Slack / Teams).
 *
 * When anonymous resolve is disabled (default), Resolve requires a Beacon actor
 * (mapped Slack user + triage, or a future Teams OpenUri flow). Possession of a
 * destination signing secret alone must not mutate issues.
 */
final readonly class HookMutationPolicy
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    public function allowAnonymousResolve(): bool
    {
        return $this->opsDefaults->allowAnonymousResolve();
    }
}
