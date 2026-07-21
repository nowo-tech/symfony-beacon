<?php

declare(strict_types=1);

namespace App\Issues;

/**
 * Validated list sorting for the project issues index (query string `sort` / `dir`).
 */
final readonly class IssueListSort
{
    public const string DEFAULT_FIELD = 'last_seen';
    public const string DEFAULT_DIRECTION = 'desc';

    /** @var list<string> */
    public const array FIELDS = [
        'title',
        'level',
        'assignee',
        'events',
        'events_24h',
        'events_7d',
        'events_30d',
        'first_seen',
        'last_seen',
    ];

    /** Fields sorted in SQL on the issue entity (or assignee join). */
    private const array SQL_FIELDS = [
        'title',
        'level',
        'assignee',
        'events',
        'first_seen',
        'last_seen',
    ];

    public function __construct(
        public string $field,
        public string $direction,
    ) {
    }

    public static function fromQuery(?string $sort, ?string $dir): self
    {
        $field = \is_string($sort) && \in_array($sort, self::FIELDS, true)
            ? $sort
            : self::DEFAULT_FIELD;

        $direction = \is_string($dir) && \in_array(strtolower($dir), ['asc', 'desc'], true)
            ? strtolower($dir)
            : self::defaultDirectionFor($field);

        return new self($field, $direction);
    }

    public static function defaultDirectionFor(string $field): string
    {
        return \in_array($field, ['title', 'level', 'assignee'], true) ? 'asc' : 'desc';
    }

    public function isSqlSortable(): bool
    {
        return \in_array($this->field, self::SQL_FIELDS, true);
    }

    public function isOccurrenceSortable(): bool
    {
        return \in_array($this->field, ['events_24h', 'events_7d', 'events_30d'], true);
    }

    public function toggledDirection(): string
    {
        return 'asc' === $this->direction ? 'desc' : 'asc';
    }

    /**
     * Next sort state when the user clicks a column header.
     */
    public function forColumnClick(string $column): self
    {
        if ($column === $this->field) {
            return new self($this->field, $this->toggledDirection());
        }

        return new self($column, self::defaultDirectionFor($column));
    }
}
