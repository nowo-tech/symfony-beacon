<?php

declare(strict_types=1);

namespace App\Api\Read\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string for {@see \App\Api\Read\Controller\ProjectReadApiController::listIssues()}.
 */
final class ProjectIssuesListQuery
{
    public function __construct(
        #[Assert\Range(min: 1, max: 1000)]
        public int $limit = 100,
        #[Assert\Length(max: 200)]
        public ?string $q = null,
        #[Assert\Length(max: 32)]
        public ?string $level = null,
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Choice(choices: ['unresolved', 'resolved', 'ignored']),
        ])]
        public ?string $status = null,
        #[Assert\Length(max: 128)]
        public ?string $environment = null,
        #[Assert\Length(max: 128)]
        public ?string $release = null,
    ) {
    }
}
