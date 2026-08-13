<?php

declare(strict_types=1);

namespace App\Project\Dto;

use App\Project\Entity\Project;
use App\Project\Service\AccessibleProjectFilter;

/**
 * Shared {@code projectChoices()} for dashboard filter DTOs that expose accessible projects.
 *
 * @property list<Project> $accessibleProjects
 */
trait AccessibleProjectFilterTrait
{
    /**
     * @return array<string, string>
     */
    public function projectChoices(): array
    {
        return AccessibleProjectFilter::choiceMap($this->accessibleProjects);
    }
}
