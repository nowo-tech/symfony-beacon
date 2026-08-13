<?php

declare(strict_types=1);

namespace App\Issues\Dto;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;

/**
 * Resolved dashboard Assignments filters and supporting choice data.
 */
final readonly class DashboardAssignmentsFilters
{
    /**
     * @param list<Project> $accessibleProjects
     * @param list<Project> $selectedProjects
     * @param list<User>    $teammates
     */
    public function __construct(
        public array $accessibleProjects,
        public array $selectedProjects,
        public array $teammates,
        public AssignmentScope $scope,
        public ?Project $project,
        public ?string $query,
        public ?string $level,
        public IssueStatus $status,
        public ?IssuePriority $priority,
        public ?User $assignee,
        public IssueListSort $sort,
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
     * @return array<string, string>
     */
    public function teammateChoices(): array
    {
        $choices = [];
        foreach ($this->teammates as $teammate) {
            $id = $teammate->getId();
            if (null === $id) {
                continue;
            }

            $choices[(string) $id] = $teammate->getDisplayName() ?: $teammate->getEmail();
        }

        return $choices;
    }

    /**
     * @return array<string, int|string>
     */
    public function formData(int $perPage): array
    {
        return [
            'scope' => $this->scope->value,
            'project' => $this->project?->getUuid() ?? '',
            'q' => $this->query ?? '',
            'level' => $this->level ?? '',
            'status' => $this->status->value,
            'priority' => $this->priority?->value ?? '',
            'assignee' => null !== $this->assignee?->getId() ? (string) $this->assignee->getId() : '',
            'sort' => $this->sort->field,
            'dir' => $this->sort->direction,
            'page' => 1,
            'per_page' => $perPage,
        ];
    }
}
