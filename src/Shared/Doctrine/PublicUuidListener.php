<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Ensures entities using {@see PublicUuidTrait} always have a UUID before insert.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class PublicUuidListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (\is_object($entity) && method_exists($entity, 'ensureUuid')) {
            $entity->ensureUuid();
        }
    }
}
