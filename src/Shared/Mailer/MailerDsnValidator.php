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
     * Schemes accepted for instance Mailer DSN (defense-in-depth beyond Transport::fromDsn).
     *
     * @var list<string>
     */
    public const ALLOWED_SCHEMES = [
        'smtp',
        'smtps',
        'sendmail',
        'native',
        'mailgun',
        'mailgun+api',
        'mailgun+smtp',
        'postmark',
        'postmark+api',
        'postmark+smtp',
        'sendgrid',
        'sendgrid+api',
        'sendgrid+smtp',
        'ses',
        'ses+api',
        'ses+smtp',
        'ses+https',
        'brevo',
        'brevo+api',
        'brevo+smtp',
        'resend',
        'resend+api',
        'resend+smtp',
        'mailjet',
        'mailjet+api',
        'mailjet+smtp',
    ];

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

        $scheme = strtolower((string) (parse_url($dsn, \PHP_URL_SCHEME) ?? ''));
        if ('' === $scheme || !\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return 'instance_mailer.mailer_dsn.scheme_not_allowed';
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

        if (null !== $this->validatePlainDsn($dsn)) {
            return false;
        }

        return true;
    }

    private function isNullTransport(string $dsn): bool
    {
        $lower = strtolower(trim($dsn));

        return str_starts_with($lower, 'null:') || str_starts_with($lower, 'null://');
    }
}
