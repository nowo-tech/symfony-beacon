<?php

declare(strict_types=1);

namespace App\Identity\Doctrine;

use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ensures every non-anonymized {@see User} persists concrete display preference columns.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class UserDisplayPreferencesDefaultsListener
{
    public function __construct(
        #[Autowire('%default_locale%')]
        private string $defaultLocale,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User || $entity->isAnonymized()) {
            return;
        }

        UserDisplayPreferenceDefaults::applyMissing($entity, $this->defaultLocale);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User || $entity->isAnonymized()) {
            return;
        }

        if (!$this->hasMissingDisplayPreference($entity)) {
            return;
        }

        UserDisplayPreferenceDefaults::applyMissing($entity, $this->defaultLocale);

        $em = $args->getObjectManager();
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $em->getClassMetadata(User::class),
            $entity,
        );
    }

    private function hasMissingDisplayPreference(User $user): bool
    {
        return \in_array(null, [$user->getPreferredLocaleRaw(), $user->getPreferredThemeRaw(), $user->getPreferredMotionRaw(), $user->getPreferredContrastRaw(), $user->getPreferredContentWidthRaw(), $user->getPreferredUiDensityRaw(), $user->getPreferredFontScaleRaw(), $user->getPreferredSidebarRaw()], true);
    }
}
