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
 * Fail closed outside local `dev`/`test` when SiteBackup or APP_SECRET still use
 * documented local defaults from `.env.dist`.
 *
 * Applies to `prod`, `staging`, and any other non-local environment name so misnamed
 * deployments cannot keep the public `/setup` and `/_site_backup` surfaces unlocked,
 * or ship with a known `APP_SECRET` (CSRF, remember-me, magic-login signatures).
 *
 * Instance latch (not static) so FrankenPHP workers do not share mutable static state.
 *
 * Console `cache:clear` / `cache:warmup` / `assets:install` are skipped so Docker
 * `frankenphp_prod` image builds (`composer dump-env` + post-install auto-scripts) can
 * warm the cache without baking runtime SiteBackup secrets into the image. HTTP requests
 * and all other console commands still enforce the check.
 */
final class SiteBackupSecurityDefaultsGuard implements EventSubscriberInterface
{
    /**
     * Historically documented bcrypt for password "beacon-local-panel".
     * Must not appear in `.env.dist`; still rejected outside local development.
     */
    public const string LOCAL_DEV_PANEL_PASSWORD_HASH = '$2y$12$h4X4XEsjEForb/3ZYVEXkuKT6B5GHlsAVx6EwJBBpJ15WnkrptgtW';

    /** Historically documented setup token — rejected outside local development. */
    public const string LOCAL_DEV_SETUP_TOKEN = 'beacon-local-setup';

    /** Documented placeholder APP_SECRET from `.env.dist` — must be rotated outside local development. */
    public const string LOCAL_DEV_APP_SECRET = 'ChangeMePleaseUseARealSecret';
    /** @var list<string> */
    private const array SKIP_CONSOLE_COMMANDS = [
        'cache:clear',
        'cache:warmup',
        'assets:install',
    ];

    private bool $checked = false;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(default::SITE_SETUP_TOKEN)%')]
        private readonly ?string $setupToken,
        #[Autowire('%env(default::SITE_BACKUP_PASSWORD_HASH)%')]
        private readonly ?string $panelPasswordHash,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret = '',
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
        $name = $event->getCommand()?->getName();
        if (null !== $name && \in_array($name, self::SKIP_CONSOLE_COMMANDS, true)) {
            return;
        }
        $this->assertProductionSecretsSafe();
    }

    /**
     * @throws RuntimeException when a non-local environment still uses empty or documented local SiteBackup secrets
     */
    public function assertProductionSecretsSafe(): void
    {
        if ($this->isLocalDevelopmentEnvironment() || $this->checked) {
            return;
        }
        $this->checked = true;

        $token = trim((string) $this->setupToken);
        if ('' === $token) {
            throw new RuntimeException('SITE_SETUP_TOKEN must be set outside local development (dev/test) so /setup is not anonymously writable. Generate a random secret and open /setup?token=… (see docs/PRODUCTION.md).');
        }
        if (hash_equals(self::LOCAL_DEV_SETUP_TOKEN, $token)) {
            throw new RuntimeException('SITE_SETUP_TOKEN is still the documented local default (beacon-local-setup). Set a unique secret before exposing this instance (docs/PRODUCTION.md).');
        }

        $hash = trim((string) $this->panelPasswordHash);
        if ('' === $hash) {
            throw new RuntimeException('SITE_BACKUP_PASSWORD_HASH must be set outside local development (dev/test) for /_site_backup. Generate with: bin/console nowo:site-backup:hash-password');
        }
        if (hash_equals(self::LOCAL_DEV_PANEL_PASSWORD_HASH, $hash)) {
            throw new RuntimeException('SITE_BACKUP_PASSWORD_HASH is still the documented local default (password "beacon-local-panel"). Generate a new hash with: bin/console nowo:site-backup:hash-password');
        }

        $secret = trim($this->appSecret);
        if ('' === $secret) {
            throw new RuntimeException('APP_SECRET must be set outside local development (dev/test). Generate a random value (e.g. openssl rand -hex 32) — see docs/PRODUCTION.md.');
        }
        if (hash_equals(self::LOCAL_DEV_APP_SECRET, $secret)) {
            throw new RuntimeException('APP_SECRET is still the documented local default (ChangeMePleaseUseARealSecret). Set a unique secret before exposing this instance (docs/PRODUCTION.md).');
        }
        if (\strlen($secret) < 16) {
            throw new RuntimeException('APP_SECRET must be at least 16 characters outside local development (dev/test).');
        }
    }

    private function isLocalDevelopmentEnvironment(): bool
    {
        return \in_array($this->environment, ['dev', 'test'], true);
    }
}
