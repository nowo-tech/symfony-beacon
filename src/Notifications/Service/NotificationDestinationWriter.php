<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists notification destination mutations (create / toggle / resume / delete).
 *
 * Controllers own HTTP forms, flashes, and redirects.
 */
final readonly class NotificationDestinationWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Project $project, NotificationDestination $destination): void
    {
        $destination->setProject($project);
        $project->addNotificationDestination($destination);
        $this->entityManager->persist($destination);
        $this->entityManager->flush();
    }

    public function update(NotificationDestination $destination): void
    {
        $this->entityManager->flush();
    }

    public function toggleEnabled(NotificationDestination $destination): void
    {
        $destination->setEnabled(!$destination->isEnabled());
        $this->entityManager->flush();
    }

    public function resumeCircuit(NotificationDestination $destination): void
    {
        $destination->resumeCircuit();
        $this->entityManager->flush();
    }

    public function delete(NotificationDestination $destination): void
    {
        $this->entityManager->remove($destination);
        $this->entityManager->flush();
    }
}
