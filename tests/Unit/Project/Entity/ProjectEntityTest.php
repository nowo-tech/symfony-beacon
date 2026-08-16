<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Entity;

use App\Issues\Entity\Issue;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class ProjectEntityTest extends TestCase
{
    public function testExposesIssuesCollectionAndRemovesThresholdRules(): void
    {
        $project = (new Project())->setName('Beacon')->setSlug('beacon');
        $rule = (new ProjectThresholdRule())->setProject($project)->setLabel('Burst');

        self::assertCount(0, $project->getIssues());
        self::assertContainsOnlyInstancesOf(Issue::class, $project->getIssues()->toArray());

        $project->addThresholdRule($rule);
        self::assertCount(1, $project->getThresholdRules());
        self::assertSame($project, $rule->getProject());

        $project->removeThresholdRule($rule);
        self::assertCount(0, $project->getThresholdRules());
    }
}
