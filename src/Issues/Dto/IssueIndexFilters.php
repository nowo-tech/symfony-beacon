<?php

declare(strict_types=1);

namespace App\Issues\Dto;

use App\Identity\Entity\User;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;

/**
 * Resolved per-project issue index filters.
 */
final readonly class IssueIndexFilters
{
    /**
     * @param list<User> $members
     */
    public function __construct(
        public array $members,
        public ?string $query,
        public ?string $level,
        public IssueStatus $status,
        public ?IssuePriority $priority,
        public ?string $environment,
        public ?string $release,
        public ?string $compare,
        public ?string $tag,
        public ?string $url,
        public ?string $user,
        public ?User $assignee,
        public bool $unassignedOnly,
        public string $assigneeFilter,
        public IssueListSort $sort,
    ) {
    }

    /**
     * Map of member id => display label (PHP coerces numeric string keys to int).
     *
     * @return array<int|string, string>
     */
    public function memberChoices(): array
    {
        $choices = [];
        foreach ($this->members as $member) {
            $id = $member->getId();
            if (null === $id) {
                continue;
            }

            $choices[(string) $id] = $member->getDisplayName() ?: $member->getEmail();
        }

        return $choices;
    }

    /**
     * @return array<string, int|string>
     */
    public function formData(int $page, int $perPage): array
    {
        return [
            'q' => $this->query ?? '',
            'level' => $this->level ?? '',
            'status' => $this->status->value,
            'environment' => $this->environment ?? '',
            'release' => $this->release ?? '',
            'compare' => $this->compare ?? '',
            'tag' => $this->tag ?? '',
            'url' => $this->url ?? '',
            'user' => $this->user ?? '',
            'priority' => $this->priority instanceof IssuePriority ? $this->priority->value : '',
            'assignee' => $this->assigneeFilter,
            'sort' => $this->sort->field,
            'dir' => $this->sort->direction,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
