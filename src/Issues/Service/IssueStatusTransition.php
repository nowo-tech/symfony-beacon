<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Enum\IssueStatus;
use InvalidArgumentException;

/**
 * Allowed issue status transitions (lightweight workflow without symfony/workflow).
 *
 * Unresolved ↔ Resolved ↔ Ignored; Ignored → Unresolved (reopen); same status is a no-op
 * handled by callers before invoking this guard.
 */
final class IssueStatusTransition
{
    /**
     * @return list<IssueStatus>
     */
    public static function allowedTargets(IssueStatus $from): array
    {
        return match ($from) {
            IssueStatus::Unresolved => [IssueStatus::Resolved, IssueStatus::Ignored],
            IssueStatus::Resolved => [IssueStatus::Unresolved, IssueStatus::Ignored],
            IssueStatus::Ignored => [IssueStatus::Unresolved, IssueStatus::Resolved],
        };
    }

    public static function canTransition(IssueStatus $from, IssueStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return \in_array($to, self::allowedTargets($from), true);
    }

    /**
     * @throws InvalidArgumentException when the transition is not allowed
     */
    public static function assertCanTransition(IssueStatus $from, IssueStatus $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new InvalidArgumentException(\sprintf('Invalid issue status transition from "%s" to "%s".', $from->value, $to->value));
        }
    }

    private function __construct()
    {
    }
}
