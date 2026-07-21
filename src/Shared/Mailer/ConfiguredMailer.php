<?php

declare(strict_types=1);

namespace App\Shared\Mailer;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves Symfony Mailer from encrypted instance settings (DB), with env fallback.
 */
final class ConfiguredMailer implements MailerInterface, ResetInterface
{
    private ?MailerInterface $resolved = null;
    private ?string $resolvedDsn = null;

    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $envMailerDsn,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->mailer()->send($message, $envelope);
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

        $this->resolved = new Mailer(Transport::fromDsn($dsn));
        $this->resolvedDsn = $dsn;

        return $this->resolved;
    }

    private function settings(): InstanceSettings
    {
        return $this->settingsRepository->getOrCreate();
    }
}
