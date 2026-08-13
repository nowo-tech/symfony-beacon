<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\Access\ProjectAccess;
use App\Project\Enum\ProjectRole;
use App\Project\Enum\ProjectSettingsSection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectSettingsSectionTest extends TestCase
{
    #[Test]
    public function routeRequirementListsAllSlugs(): void
    {
        self::assertSame('general|access|alerts|data|danger', ProjectSettingsSection::routeRequirement());
        self::assertSame('project.settings.tab.general', ProjectSettingsSection::General->tabLabelKey());
    }

    #[Test]
    public function ownerSeesAllSections(): void
    {
        $access = new ProjectAccess(ProjectRole::Owner);
        $visible = ProjectSettingsSection::visibleFor($access);

        self::assertSame(ProjectSettingsSection::cases(), $visible);
        self::assertSame(ProjectSettingsSection::General, ProjectSettingsSection::defaultFor($access));
    }

    #[Test]
    public function adminSeesDangerAndDataTabs(): void
    {
        $access = new ProjectAccess(ProjectRole::Admin);
        self::assertTrue(ProjectSettingsSection::Danger->isVisibleFor($access));
        self::assertTrue(ProjectSettingsSection::Data->isVisibleFor($access));
        self::assertSame(ProjectSettingsSection::General, ProjectSettingsSection::defaultFor($access));
    }
}
