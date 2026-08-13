<?php

declare(strict_types=1);

namespace App\Issues\Form;

/**
 * Shared choice values for issue list GET filters (index + dashboard assignments).
 */
final class IssueListFilterFields
{
    /** @var list<string> */
    public const array LEVELS = ['fatal', 'error', 'warning', 'info', 'debug'];

    /** @var list<string> */
    public const array STATUSES = ['unresolved', 'resolved', 'ignored'];

    /** @var list<string> */
    public const array PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Priority choices with an empty "any" option, keyed by translation id prefix.
     *
     * @return array<string, string>
     */
    public static function priorityChoicesWithAny(string $translationPrefix): array
    {
        $choices = [$translationPrefix.'.priority.any' => ''];
        foreach (self::PRIORITIES as $priority) {
            $choices[$translationPrefix.'.priority.'.$priority] = $priority;
        }

        return $choices;
    }

    /**
     * Status choices with an empty "any" option (project issue index).
     *
     * @return array<string, string>
     */
    public static function statusChoicesWithAny(string $translationPrefix): array
    {
        $choices = [$translationPrefix.'.status.any' => ''];
        foreach (self::STATUSES as $status) {
            $choices[$translationPrefix.'.status.'.$status] = $status;
        }

        return $choices;
    }

    /**
     * Identity map for level select (label === value).
     *
     * @return array<string, string>
     */
    public static function levelIdentityChoices(): array
    {
        return array_combine(self::LEVELS, self::LEVELS);
    }

    /**
     * Identity map for status select without "any".
     *
     * @return array<string, string>
     */
    public static function statusIdentityChoices(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }
}
