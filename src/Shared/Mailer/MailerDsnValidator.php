<?php

declare(strict_types=1);

namespace App\Shared\Mailer;

use Symfony\Component\Mailer\Transport;
use Throwable;

/**
 * Validates Symfony Mailer DSNs used for magic-login and notification email delivery.
 */
final class MailerDsnValidator
{
    /**
     * @return non-empty-string|null Translation key when invalid; null when OK
     */
    public function validatePlainDsn(string $dsn): ?string
    {
        $dsn = trim($dsn);
        if ('' === $dsn) {
            return null;
        }

        if ($this->isNullTransport($dsn)) {
            return 'instance_mailer.mailer_dsn.null_transport';
        }

        try {
            Transport::fromDsn($dsn);
        } catch (Throwable) {
            return 'instance_mailer.mailer_dsn.invalid';
        }

        return null;
    }

    public function isDeliverable(string $dsn): bool
    {
        $dsn = trim($dsn);
        if ('' === $dsn || $this->isNullTransport($dsn)) {
            return false;
        }

        try {
            Transport::fromDsn($dsn);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function isNullTransport(string $dsn): bool
    {
        $lower = strtolower(trim($dsn));

        return str_starts_with($lower, 'null:') || str_starts_with($lower, 'null://');
    }
}
