<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use App\Project\Dto\AccessibleProjectFilterTrait;
use App\Project\Entity\Project;

/**
 * Resolved dashboard Activity filters and helper payloads.
 */
final readonly class DashboardActivityFilters
{
    use AccessibleProjectFilterTrait;

    /**
     * @param list<Project> $accessibleProjects
     * @param list<string>  $projectUuids
     */
    public function __construct(
        public array $accessibleProjects,
        public array $projectUuids,
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
