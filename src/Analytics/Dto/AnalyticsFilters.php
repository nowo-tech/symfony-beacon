<?php

declare(strict_types=1);

namespace App\Analytics\Dto;

/**
 * Resolved Analytics page filters (period + optional series dimensions).
 */
final readonly class AnalyticsFilters
{
    public function __construct(
        public ?string $environment,
        public ?string $release,
        public ?string $level,
    ) {
    }

    public static function fromRequestQuery(string $environment, string $release, string $level): self
    {
        return new self(
            environment: '' !== $environment ? $environment : null,
            release: '' !== $release ? $release : null,
            level: '' !== $level ? $level : null,
        );
    }

    /**
     * @return array{environment: string, release: string, level: string}
     */
    public function formData(): array
    {
        return [
            'environment' => $this->environment ?? '',
            'release' => $this->release ?? '',
            'level' => $this->level ?? '',
        ];
    }
}
