<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Service\ProductTourStepsBuilder;
use App\Identity\Tour\ProductTourContext;
use App\Identity\Tour\ProductTourPage;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;

final class ProductTourStepsBuilderTest extends DatabaseWebTestCase
{
    public function testBuildFiltersStepsByPermissions(): void
    {
        $this->bootWithDemoProject('tour-steps@example.com');
        $builder = self::getContainer()->get(ProductTourStepsBuilder::class);

        $dashboardAdmin = $builder->build(new ProductTourContext(
            ProductTourPage::Dashboard,
            isInstanceAdmin: true,
            canCreateProject: true,
        ));
        self::assertGreaterThanOrEqual(5, \count($dashboardAdmin));
        self::assertTrue($this->hasElement($dashboardAdmin, '[data-tour="admin-link"]'));
        self::assertTrue($this->hasElement($dashboardAdmin, '[data-tour="new-project"]'));

        $dashboardMember = $builder->build(new ProductTourContext(
            ProductTourPage::Dashboard,
            isInstanceAdmin: false,
            canCreateProject: false,
        ));
        self::assertFalse($this->hasElement($dashboardMember, '[data-tour="admin-link"]'));
        self::assertFalse($this->hasElement($dashboardMember, '[data-tour="new-project"]'));

        $projectAdmin = $builder->build(new ProductTourContext(
            ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: true,
            projectRole: ProjectRole::Admin,
        ));
        self::assertTrue($this->hasElement($projectAdmin, '[data-tour="issue-saved-views"]'));
        self::assertTrue($this->hasElement($projectAdmin, '[data-tour="project-settings"]'));

        $admin = $builder->build(new ProductTourContext(
            ProductTourPage::Admin,
            isInstanceAdmin: true,
            canCreateProject: true,
        ));
        self::assertCount(5, $admin);
    }

    /**
     * @param list<array{element?: string, popover: array<string, string>}> $steps
     */
    private function hasElement(array $steps, string $selector): bool
    {
        return array_any($steps, static fn (array $step): bool => ($step['element'] ?? null) === $selector);
    }
}
