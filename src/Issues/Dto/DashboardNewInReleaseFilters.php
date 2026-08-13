<?php

declare(strict_types=1);

namespace App\Issues\Dto;

use App\Project\Dto\AccessibleProjectFilterTrait;
use App\Project\Entity\Project;

/**
 * Resolved dashboard New-in-release filters and available choices.
 */
final readonly class DashboardNewInReleaseFilters
{
    use AccessibleProjectFilterTrait;

    /**
     * @param list<Project> $accessibleProjects
     * @param list<Project> $selectedProjects
     * @param list<string>  $availableReleases
     */
    public function __construct(
        public array $accessibleProjects,
        public array $selectedProjects,
        public array $availableReleases,
        public ?Project $project,
        public ?string $release,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function releaseChoices(): array
    {
        return array_combine($this->availableReleases, $this->availableReleases);
    }

    /**
     * @return array<string, int|string>
     */
    public function formData(int $perPage): array
    {
        return [
            'project' => $this->project?->getUuid() ?? '',
            'release' => $this->release ?? '',
            'page' => 1,
            'per_page' => $perPage,
        ];
    }
}
