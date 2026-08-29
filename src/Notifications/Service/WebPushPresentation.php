<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Enum\MemberAlertEvent;

/**
 * Resolves Web Push notification chrome ({@code title}/{@code body}/{@code tag}/…) so the
 * PwaBundle kit SW ({@code service_worker.web_push}) can stay product-agnostic.
 *
 * @phpstan-type RealtimePayload array<string, mixed>
 */
final class WebPushPresentation
{
    private const int PREVIEW_MAX = 110;

    /** @var array<string, string> */
    private const array EVENT_TITLES = [
        'issue.new' => 'New issue',
        'issue.regression' => 'Issue regression',
        'issue.resolved' => 'Issue resolved',
        'issue.reopened' => 'Issue reopened',
        'issue.assigned' => 'Issue assigned',
        'issue.commented' => 'New comment',
    ];

    /**
     * @param RealtimePayload $payload
     *
     * @return RealtimePayload
     */
    public function enrich(MemberAlertEvent $event, array $payload): array
    {
        $eventKey = $event->value;
        $payload['event'] = $eventKey;
        $payload['title'] = self::EVENT_TITLES[$eventKey] ?? 'New alert';
        $payload['body'] = $this->body($payload, $payload['title']);
        $payload['tag'] = $this->tag($payload);
        $payload['icon'] ??= '/icons/icon-192.png';
        $payload['badge'] ??= '/icons/icon-192.png';
        if (!isset($payload['url']) || !\is_string($payload['url']) || '' === $payload['url']) {
            $payload['url'] = '/dashboard';
        }

        return $payload;
    }

    /**
     * @param RealtimePayload $payload
     */
    private function body(array $payload, string $title): string
    {
        $issue = \is_array($payload['issue'] ?? null) ? $payload['issue'] : [];
        $project = \is_array($payload['project'] ?? null) ? $payload['project'] : [];
        $preview = $this->issuePreview(
            isset($issue['title']) && \is_string($issue['title']) ? $issue['title'] : null,
            isset($issue['culprit']) && \is_string($issue['culprit']) ? $issue['culprit'] : null,
        );
        $projectName = isset($project['name']) && \is_string($project['name']) ? trim($project['name']) : '';

        if ('' !== $projectName && '' !== $preview) {
            return $projectName.' · '.$preview;
        }
        if ('' !== $preview) {
            return $preview;
        }
        if ('' !== $projectName) {
            return $projectName;
        }

        $summary = isset($payload['summary']) && \is_string($payload['summary']) ? $payload['summary'] : '';
        if ('' !== $summary && $summary !== $title) {
            return $this->truncate($summary, self::PREVIEW_MAX);
        }

        return $title;
    }

    /**
     * @param RealtimePayload $payload
     */
    private function tag(array $payload): string
    {
        $issue = \is_array($payload['issue'] ?? null) ? $payload['issue'] : [];
        $uuid = isset($issue['uuid']) && \is_string($issue['uuid']) ? $issue['uuid'] : '';

        return '' !== $uuid ? 'issue-'.$uuid : 'beacon-issue';
    }

    private function issuePreview(?string $title, ?string $culprit): string
    {
        $text = trim((string) $title);
        if ('' === $text) {
            $text = trim((string) $culprit);
        }
        if ('' === $text) {
            return '';
        }

        $firstLine = preg_split('/\r?\n/', $text)[0] ?? $text;
        $text = trim((string) $firstLine);
        $text = (string) preg_replace('/^((?:[A-Za-z_][\w$]*\\\\)+)([A-Za-z_][\w$]*)\b/', '$2', $text);

        return $this->truncate($text, self::PREVIEW_MAX);
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }
}
