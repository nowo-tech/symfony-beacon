<?php

declare(strict_types=1);

namespace App\Setup;

use Nowo\SiteBackupBundle\Event\SetupCompletedEvent;
use Nowo\SiteBackupBundle\Setup\DurableSetupDoneStoreInterface;
use Nowo\SiteBackupBundle\Setup\Storage\SetupMarkerManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps durable done + ephemeral {@code setup.done} in sync when SiteBackup finishes.
 */
#[AsEventListener(event: SetupCompletedEvent::class)]
final readonly class SetupCompletedSubscriber
{
    public function __construct(
        private DurableSetupDoneStoreInterface $durableSetupDoneStore,
        private SetupMarkerManager $markers,
    ) {
    }

    public function __invoke(): void
    {
        $this->durableSetupDoneStore->markDone();
        // Durable signal is setup_completed_at; keep the file marker aligned when present.
        $this->markers->markDone();
    }
}
