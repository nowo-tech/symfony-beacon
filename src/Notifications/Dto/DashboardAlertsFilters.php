<?php

declare(strict_types=1);

namespace App\Notifications\Dto;

use App\Project\Dto\AccessibleProjectFilterTrait;
use App\Project\Entity\Project;

/**
 * Resolved dashboard Alerts filters and helper payloads.
 */
final readonly class DashboardAlertsFilters
{
    use AccessibleProjectFilterTrait;

    /**
     * @param list<Project> $accessibleProjects
     * @param list<Project> $selectedProjects
     */
    public function __construct(
        public array $accessibleProjects,
        public array $selectedProjects,
        public ?Project $project,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function formData(int $perPage): array
    {
        return [
            'project' => $this->project?->getUuid() ?? '',
            'per_page' => $perPage,
            'page' => 1,
        ];
    }
}
