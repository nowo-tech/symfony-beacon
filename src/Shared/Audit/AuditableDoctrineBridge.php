<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Nowo\AuditKitBundle\Doctrine\AuditableEntityListener;

/**
 * Bridges AuditKit's entity listener to Doctrine lifecycle events.
 *
 * AuditKit tags {@see AuditableEntityListener} with `entity: null`, which
 * DoctrineBundle 3 does not attach to any entity metadata. This bridge
 * registers as a global event listener instead.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class AuditableDoctrineBridge
{
    public function __construct(
        private AuditableEntityListener $listener,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->listener->prePersist($args->getObject(), $args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->listener->preUpdate($args->getObject(), $args);
    }
}
