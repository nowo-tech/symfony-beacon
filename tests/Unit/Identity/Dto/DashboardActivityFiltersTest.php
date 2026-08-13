<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Dto;

use App\Identity\Dto\DashboardActivityFilters;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class DashboardActivityFiltersTest extends TestCase
{
    public function testFormDataAndProjectChoices(): void
    {
        $project = new Project();
        $project->setSlug('activity');
        $project->setName('Activity');

        $filters = new DashboardActivityFilters([$project], [$project->getUuid()], $project);

        self::assertSame(
            [
                'project' => $project->getUuid(),
                'per_page' => 30,
                'page' => 1,
            ],
            $filters->formData(30),
        );
        self::assertSame(['Activity' => $project->getUuid()], $filters->projectChoices());
    }

    public function testFormDataWithoutSelectedProject(): void
    {
        $filters = new DashboardActivityFilters([], [], null);

        self::assertSame(
            [
                'project' => '',
                'per_page' => 10,
                'page' => 1,
            ],
            $filters->formData(10),
        );
        self::assertSame([], $filters->projectChoices());
    }
}
