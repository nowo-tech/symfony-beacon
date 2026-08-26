<?php

declare(strict_types=1);

namespace App\Issues\Dto;

/**
 * Derived database/query diagnostics for issue/event UI (not persisted).
 */
final readonly class QueryFacts
{
    public const string SOURCE_STRUCTURED = 'structured';
    public const string SOURCE_BREADCRUMB = 'breadcrumb';
    public const string SOURCE_EXCEPTION = 'exception_message';

    /**
     * @param list<mixed>|array<string, mixed>|null $bindings
     */
    public function __construct(
        public ?string $sqlstate = null,
        public ?string $vendorCode = null,
        public ?string $driver = null,
        public ?string $sqlMode = null,
        public ?string $sql = null,
        public mixed $bindings = null,
        public string $source = self::SOURCE_EXCEPTION,
        public bool $sqlTruncated = false,
        public ?string $summary = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->sqlstate && null === $this->vendorCode && (null === $this->sql || '' === $this->sql);
    }

    /**
     * @return array{
     *     sqlstate: ?string,
     *     vendor_code: ?string,
     *     driver: ?string,
     *     sql_mode: ?string,
     *     sql: ?string,
     *     bindings: mixed,
     *     source: string,
     *     sql_truncated: bool,
     *     summary: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'sqlstate' => $this->sqlstate,
            'vendor_code' => $this->vendorCode,
            'driver' => $this->driver,
            'sql_mode' => $this->sqlMode,
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'source' => $this->source,
            'sql_truncated' => $this->sqlTruncated,
            'summary' => $this->summary,
        ];
    }
}
