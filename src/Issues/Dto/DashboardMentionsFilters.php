<?php

declare(strict_types=1);

namespace App\Issues\Dto;

use App\Project\Entity\Project;

/**
 * Resolved dashboard Mentions filters and helper payloads.
 */
final readonly class DashboardMentionsFilters
{
    /**
     * @param list<Project> $accessibleProjects
     * @param list<Project> $selectedProjects
     */
    public function __construct(
        public array $accessibleProjects,
        public array $selectedProjects,
        public ?Project $project,
        public bool $unreadOnly,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function projectChoices(): array
    {
        $choices = [];
        foreach ($this->accessibleProjects as $project) {
            $choices[$project->getName()] = $project->getUuid();
        }

        return $choices;
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function formData(int $perPage): array
    {
        return [
            'project' => $this->project?->getUuid() ?? '',
            'unread' => $this->unreadOnly,
            'page' => 1,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function redirectQuery(int $perPage): array
    {
        return array_filter(
            [
                'project' => $this->project?->getUuid() ?? '',
                'unread' => $this->unreadOnly ? '1' : '',
                'per_page' => (string) $perPage,
            ],
            static fn (string $value): bool => '' !== $value,
        );
    }
}
