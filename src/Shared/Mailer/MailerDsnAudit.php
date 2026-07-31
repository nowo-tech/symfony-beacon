<?php

declare(strict_types=1);

namespace App\Shared\Mailer;

/**
 * Builds redacted Mailer DSN facts for {@see \App\Identity\UserAction} context.
 *
 * Never includes userinfo, password, query, or the full DSN.
 */
final class MailerDsnAudit
{
    /**
     * @return array{scheme: ?string, host: ?string}
     */
    public static function redact(?string $dsn): array
    {
        if (null === $dsn) {
            return ['scheme' => null, 'host' => null];
        }

        $dsn = trim($dsn);
        if ('' === $dsn) {
            return ['scheme' => null, 'host' => null];
        }

        $parts = parse_url($dsn);
        if (!\is_array($parts)) {
            return ['scheme' => null, 'host' => null];
        }

        $scheme = isset($parts['scheme']) && \is_string($parts['scheme']) && '' !== $parts['scheme']
            ? strtolower($parts['scheme'])
            : null;
        $host = isset($parts['host']) && \is_string($parts['host']) && '' !== $parts['host']
            ? $parts['host']
            : null;

        return ['scheme' => $scheme, 'host' => $host];
    }
}
