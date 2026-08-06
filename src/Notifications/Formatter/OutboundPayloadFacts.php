<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

/**
 * Shared outbound payload helpers used across notification channels.
 */
final class OutboundPayloadFacts
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{title: string, value: string, short: bool}>
     */
    public static function factFields(array $payload): array
    {
        $fields = [];
        $project = $payload['project'] ?? null;
        if (\is_array($project) && isset($project['name']) && \is_scalar($project['name'])) {
            $fields[] = ['title' => 'Project', 'value' => (string) $project['name'], 'short' => true];
        }

        $issue = $payload['issue'] ?? null;
        if (\is_array($issue)) {
            if (isset($issue['level']) && \is_scalar($issue['level'])) {
                $fields[] = ['title' => 'Level', 'value' => (string) $issue['level'], 'short' => true];
            }
            if (isset($issue['title']) && \is_scalar($issue['title'])) {
                $fields[] = ['title' => 'Issue', 'value' => (string) $issue['title'], 'short' => false];
            }
            if (isset($issue['culprit']) && \is_scalar($issue['culprit']) && '' !== (string) $issue['culprit']) {
                $fields[] = ['title' => 'Culprit', 'value' => (string) $issue['culprit'], 'short' => false];
            }
        }

        if (true === ($payload['test'] ?? false)) {
            $fields[] = ['title' => 'Sample', 'value' => 'yes', 'short' => true];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{name: string, value: string, inline: bool}>
     */
    public static function discordFields(array $payload): array
    {
        $fields = [];
        foreach (self::factFields($payload) as $field) {
            $fields[] = [
                'name' => $field['title'],
                'value' => $field['value'],
                'inline' => $field['short'],
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function plainTextBody(array $payload, string $summary): string
    {
        $lines = [$summary];
        foreach (self::factFields($payload) as $field) {
            if ('Sample' === $field['title']) {
                continue;
            }
            $lines[] = $field['title'].': '.$field['value'];
        }
        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $lines[] = '';
            $lines[] = $payload['url'];
        }

        return implode("\n", $lines);
    }
}
