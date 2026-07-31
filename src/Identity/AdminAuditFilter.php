<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Parse Admin audit timeline query filters (`action`, `from`, `to`).
 */
final class AdminAuditFilter
{
    /**
     * @param list<UserActionType> $allowedActions
     *
     * @return array{
     *     action: ?UserActionType,
     *     from: ?DateTimeImmutable,
     *     to: ?DateTimeImmutable,
     *     filter: array{action: string, from: string, to: string}
     * }
     */
    public static function fromRequest(Request $request, array $allowedActions): array
    {
        $rawAction = $request->query->getString('action');
        $rawFrom = $request->query->getString('from');
        $rawTo = $request->query->getString('to');
        $action = self::resolveAction($rawAction, $allowedActions);

        return [
            'action' => $action,
            'from' => self::parseDate($rawFrom, false),
            'to' => self::parseDate($rawTo, true),
            'filter' => [
                'action' => $action instanceof UserActionType ? $action->value : '',
                'from' => $rawFrom,
                'to' => $rawTo,
            ],
        ];
    }

    /**
     * @param list<UserActionType> $allowedActions
     */
    private static function resolveAction(string $raw, array $allowedActions): ?UserActionType
    {
        if ('' === $raw) {
            return null;
        }

        foreach ($allowedActions as $action) {
            if ($action->value === $raw) {
                return $action;
            }
        }

        return null;
    }

    private static function parseDate(string $raw, bool $endOfDay): ?DateTimeImmutable
    {
        if ('' === $raw) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $date || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }
}
