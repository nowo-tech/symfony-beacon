<?php

declare(strict_types=1);

namespace App\Shared\Mailer;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves Symfony Mailer from encrypted instance settings (DB), with env fallback.
 */
final class ConfiguredMailer implements MailerInterface, ResetInterface
{
    private ?MailerInterface $resolved = null;
    private ?string $resolvedDsn = null;

    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        private readonly MailerDsnValidator $dsnValidator,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $envMailerDsn,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment = 'prod',
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->mailer()->send($message, $envelope);
    }

    /**
     * Sends a sample message to verify encrypted Mailer credentials (magic-login path).
     */
    public function sendSample(string $to, TranslatorInterface $translator): void
    {
        if (!$this->isMagicLoginAvailable()) {
            throw new RuntimeException('Mailer sample requires an encrypted non-null Mailer DSN.');
        }

        $message = new Email()
            ->from($this->getFromAddress())
            ->to($to)
            ->subject($translator->trans('mailer_settings.sample.email_subject'))
            ->text($translator->trans('mailer_settings.sample.email_body', [
                '%from%' => $this->getFromAddress(),
            ]));

        $this->send($message);
    }

    public function getFromAddress(): string
    {
        return $this->settings()->getEffectiveMailerFrom();
    }

    public function getEffectiveDsn(): string
    {
        $settings = $this->settings();
        if ($settings->hasMailerDsn()) {
            return (string) $settings->getMailerDsn();
        }

        $env = trim($this->envMailerDsn);

        return '' !== $env ? $env : 'null://null';
    }

    public function isConfiguredFromDatabase(): bool
    {
        return $this->settings()->hasMailerDsn();
    }

    /**
     * True when an encrypted instance Mailer DSN is stored and can deliver mail
     * (excludes null:// transports). Used to gate magic-link login UI and requests.
     */
    public function isMagicLoginAvailable(): bool
    {
        if (!$this->isConfiguredFromDatabase()) {
            return false;
        }

        $dsn = (string) $this->settings()->getMailerDsn();

        return $this->dsnValidator->isDeliverable($dsn);
    }

    public function reset(): void
    {
        $this->resolved = null;
        $this->resolvedDsn = null;
    }

    private function mailer(): MailerInterface
    {
        $dsn = $this->getEffectiveDsn();
        if ($this->resolved instanceof MailerInterface && $this->resolvedDsn === $dsn) {
            return $this->resolved;
        }

        // Functional tests store a non-null DSN to enable magic login, but must not open real SMTP sockets.
        $transportDsn = $dsn;
        if ('test' === $this->environment && str_starts_with(strtolower($dsn), 'smtp://')) {
            $transportDsn = 'null://null';
        }

        $this->resolved = new Mailer(Transport::fromDsn($transportDsn));
        $this->resolvedDsn = $dsn;

        return $this->resolved;
    }

    private function settings(): InstanceSettings
    {
        return $this->settingsRepository->getOrCreate();
    }
}
