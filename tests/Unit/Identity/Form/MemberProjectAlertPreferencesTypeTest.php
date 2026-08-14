<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Form;

use App\Identity\Form\MemberProjectAlertPreferencesType;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class MemberProjectAlertPreferencesTypeTest extends TestCase
{
    public function testFormNameForProjectStripsDashesFromUuid(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        self::assertSame(
            'project_alerts_'.str_replace('-', '', $project->getUuid()),
            MemberProjectAlertPreferencesType::formNameForProject($project),
        );
        self::assertSame(
            MemberProjectAlertPreferencesType::formNameForUuid($project->getUuid()),
            MemberProjectAlertPreferencesType::formNameForProject($project),
        );
    }

    public function testBlockPrefixIsStable(): void
    {
        $type = new ReflectionClass(MemberProjectAlertPreferencesType::class)->newInstanceWithoutConstructor();
        self::assertSame('member_project_alert_preferences', $type->getBlockPrefix());
    }
}
