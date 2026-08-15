<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\ProjectThresholdRule;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists threshold rule mutations (create / toggle / delete).
 *
 * Controllers own HTTP forms, flashes, and redirects.
 */
final readonly class ThresholdRuleWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Project $project, ProjectThresholdRule $rule): void
    {
        $rule->setProject($project);
        $project->addThresholdRule($rule);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function toggleEnabled(ProjectThresholdRule $rule): void
    {
        $rule->setEnabled(!$rule->isEnabled());
        $this->entityManager->flush();
    }

    public function delete(ProjectThresholdRule $rule): void
    {
        $this->entityManager->remove($rule);
        $this->entityManager->flush();
    }
}
