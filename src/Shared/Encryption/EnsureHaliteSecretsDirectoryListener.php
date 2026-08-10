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
 * Ensures {@code var/secrets/} exists before Halite auto-creates {@code .Halite.default.key}.
 *
 * {@code nowo-tech/doctrine-encrypt-bundle} writes the key file but does not create the parent
 * directory. Setup wizard / console paths that skip {@code make ensure-halite-secrets} would
 * otherwise fail with {@code file_put_contents(...): No such file or directory}.
 */
final readonly class EnsureHaliteSecretsDirectoryListener
{
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
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf('Unable to create Halite secrets directory: %s', $dir));
        }
    }
}
