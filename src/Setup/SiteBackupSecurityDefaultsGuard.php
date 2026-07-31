<?php

declare(strict_types=1);

namespace App\Setup;

use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Fail closed in production when SiteBackup still uses documented local defaults
 * (panel password hash / setup token from `.env.dist`).
 */
final class SiteBackupSecurityDefaultsGuard implements EventSubscriberInterface
{
    /** bcrypt hash for local password "beacon-local-panel" shipped in `.env.dist`. */
    public const string LOCAL_DEV_PANEL_PASSWORD_HASH = '$2y$12$h4X4XEsjEForb/3ZYVEXkuKT6B5GHlsAVx6EwJBBpJ15WnkrptgtW';

    /** Documented local setup token from `.env.dist` — must be rotated in production. */
    public const string LOCAL_DEV_SETUP_TOKEN = 'beacon-local-setup';

    private static bool $checked = false;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(default::SITE_SETUP_TOKEN)%')]
        private readonly ?string $setupToken,
        #[Autowire('%env(default::SITE_BACKUP_PASSWORD_HASH)%')]
        private readonly ?string $panelPasswordHash,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1024],
            ConsoleEvents::COMMAND => ['onConsole', 1024],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->assertProductionSecretsSafe();
    }

    public function onConsole(ConsoleCommandEvent $event): void
    {
        $this->assertProductionSecretsSafe();
    }

    /**
     * @throws RuntimeException when production still uses empty or documented local SiteBackup secrets
     */
    public function assertProductionSecretsSafe(): void
    {
        if ('prod' !== $this->environment || self::$checked) {
            return;
        }
        self::$checked = true;

        $token = trim((string) $this->setupToken);
        if ('' === $token) {
            throw new RuntimeException('SITE_SETUP_TOKEN must be set in production so /setup is not anonymously writable. Generate a random secret and open /setup?token=… (see docs/PRODUCTION.md).');
        }
        if (hash_equals(self::LOCAL_DEV_SETUP_TOKEN, $token)) {
            throw new RuntimeException('SITE_SETUP_TOKEN is still the documented local default (beacon-local-setup). Set a unique secret before exposing this instance (docs/PRODUCTION.md).');
        }

        $hash = trim((string) $this->panelPasswordHash);
        if ('' === $hash) {
            throw new RuntimeException('SITE_BACKUP_PASSWORD_HASH must be set in production for /_site_backup. Generate with: bin/console nowo:site-backup:hash-password');
        }
        if (hash_equals(self::LOCAL_DEV_PANEL_PASSWORD_HASH, $hash)) {
            throw new RuntimeException('SITE_BACKUP_PASSWORD_HASH is still the documented local default (password "beacon-local-panel"). Generate a new hash with: bin/console nowo:site-backup:hash-password');
        }
    }

    /**
     * @internal tests only — reset the once-per-process latch
     */
    public static function resetCheckedFlag(): void
    {
        self::$checked = false;
    }
}
