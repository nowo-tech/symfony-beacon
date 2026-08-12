<?php

declare(strict_types=1);

namespace App\Notifications\Enum;

/**
 * Member live/push alert event ids (opt-out matrix).
 */
enum MemberAlertEvent: string
{
    case IssueNew = 'issue.new';
    case IssueRegression = 'issue.regression';
    case IssueResolved = 'issue.resolved';
    case IssueReopened = 'issue.reopened';
    case IssueAssigned = 'issue.assigned';
    case IssueCommented = 'issue.commented';

    /**
     * @return list<self>
     */
    public static function casesInUiOrder(): array
    {
        return [
            self::IssueNew,
            self::IssueRegression,
            self::IssueResolved,
            self::IssueReopened,
            self::IssueAssigned,
            self::IssueCommented,
        ];
    }

    public function translationKey(): string
    {
        return 'preferences.member_alerts.event.'.$this->value;
    }

    /**
     * Symfony form field names cannot contain dots.
     */
    public function formKey(): string
    {
        return str_replace('.', '_', $this->value);
    }

    public static function tryFromFormKey(string $formKey): ?self
    {
        return self::tryFrom(str_replace('_', '.', $formKey));
    }

    /**
     * @param array{enabled?: bool, scope?: string} $stored
     *
     * @return array{enabled: bool, involved: bool}
     */
    public static function toFormEventRow(array $stored): array
    {
        return [
            'enabled' => \array_key_exists('enabled', $stored) ? (bool) $stored['enabled'] : true,
            'involved' => MemberAlertScope::Involved->value === ($stored['scope'] ?? MemberAlertScope::All->value),
        ];
    }

    /**
     * @param array<string, mixed> $formRow
     *
     * @return array{enabled: bool, scope: string}
     */
    public static function fromFormEventRow(array $formRow): array
    {
        $enabled = \array_key_exists('enabled', $formRow) ? (bool) $formRow['enabled'] : true;
        $involved = (bool) ($formRow['involved'] ?? false);
        if (!$involved && isset($formRow['scope']) && MemberAlertScope::Involved->value === $formRow['scope']) {
            $involved = true;
        }

        return [
            'enabled' => $enabled,
            'scope' => $involved ? MemberAlertScope::Involved->value : MemberAlertScope::All->value,
        ];
    }

    /**
     * @param array<string, array{enabled: bool, scope: string}> $eventsByValue
     *
     * @return array<string, array{enabled: bool, involved: bool}>
     */
    public static function mapEventsToFormKeys(array $eventsByValue): array
    {
        $out = [];
        foreach ($eventsByValue as $value => $row) {
            $event = self::tryFrom($value);
            $out[null !== $event ? $event->formKey() : str_replace('.', '_', $value)] = self::toFormEventRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $eventsByFormKey
     *
     * @return array<string, array{enabled: bool, scope: string}>
     */
    public static function mapEventsFromFormKeys(array $eventsByFormKey): array
    {
        $out = [];
        foreach ($eventsByFormKey as $formKey => $row) {
            $event = self::tryFromFormKey((string) $formKey);
            $key = null !== $event ? $event->value : str_replace('_', '.', (string) $formKey);
            $out[$key] = self::fromFormEventRow(\is_array($row) ? $row : []);
        }

        return $out;
    }
}
