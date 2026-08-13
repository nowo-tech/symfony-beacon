<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Dto;

use App\Notifications\Dto\DashboardAlertsFilters;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class DashboardAlertsFiltersTest extends TestCase
{
    public function testFormDataAndProjectChoices(): void
    {
        $project = new Project();
        $project->setSlug('alerts');
        $project->setName('Alerts');

        $filters = new DashboardAlertsFilters([$project], [$project], $project);

        self::assertSame(
            [
                'project' => $project->getUuid(),
                'per_page' => 25,
                'page' => 1,
            ],
            $filters->formData(25),
        );
        self::assertSame(['Alerts' => $project->getUuid()], $filters->projectChoices());
    }

    public function testFormDataWithoutSelectedProject(): void
    {
        $filters = new DashboardAlertsFilters([], [], null);

        self::assertSame(
            [
                'project' => '',
                'per_page' => 10,
                'page' => 1,
            ],
            $filters->formData(10),
        );
    }
}
