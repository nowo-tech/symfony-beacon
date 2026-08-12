<?php

declare(strict_types=1);

namespace App\Shared\Encryption;

use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures {@code var/secrets/} exists and Halite key files are mode {@code 0600}.
 *
 * {@code nowo-tech/doctrine-encrypt-bundle} writes the key file but does not create the parent
 * directory or harden permissions. World-writable keys would allow anyone with filesystem
 * access to decrypt API secrets, webhook URLs, Mailer DSN, and Mercure JWT material.
 */
final readonly class EnsureHaliteSecretsDirectoryListener
{
    private const int DIR_MODE = 0770;
    private const int KEY_MODE = 0600;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->ensure();
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND, priority: 1024)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->ensure();
    }

    private function ensure(): void
    {
        $dir = $this->projectDir.'/var/secrets';
        if (!is_dir($dir)) {
            if (!mkdir($dir, self::DIR_MODE, true) && !is_dir($dir)) {
                throw new RuntimeException(\sprintf('Unable to create Halite secrets directory: %s', $dir));
            }
        }

        $this->hardenKeyFiles($dir);
    }

    private function hardenKeyFiles(string $dir): void
    {
        $pattern = $dir.'/.Halite*.key';
        foreach (glob($pattern) ?: [] as $keyFile) {
            if (!is_file($keyFile)) {
                continue;
            }

            $perms = fileperms($keyFile);
            if (false === $perms) {
                continue;
            }

            // Already owner read/write only (ignore higher bits).
            if ((self::KEY_MODE & 0777) === ($perms & 0777)) {
                continue;
            }

            if (!@chmod($keyFile, self::KEY_MODE)) {
                // Best-effort: directory may be read-only in some CI images.
                continue;
            }
        }
    }
}
