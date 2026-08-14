<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Issues\Controller\IssueController;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class IssueControllerHelpersTest extends TestCase
{
    public function testFilterQueryFromArrayKeepsKnownNonEmptyKeys(): void
    {
        $controller = new ReflectionClass(IssueController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(IssueController::class, 'filterQueryFromArray');

        $query = $method->invoke($controller, [
            'q' => ' boom ',
            'level' => 'error',
            'status' => '',
            'environment' => null,
            'per_page' => '50',
            'sort' => 'last_seen',
            'unknown' => 'drop-me',
            'page' => 2,
        ]);

        self::assertSame([
            'q' => ' boom ',
            'level' => 'error',
            'sort' => 'last_seen',
            'per_page' => 50,
        ], $query);
    }

    public function testBuildEnvironmentComparePartitionsIssues(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $onlyA = $this->issue($project, 1, 'A-only');
        $both = $this->issue($project, 2, 'Both');
        $onlyB = $this->issue($project, 3, 'B-only');

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('findByLastEnvironment')->willReturnCallback(
            static fn (Project $p, string $environment): array => match ($environment) {
                'prod' => [$onlyA, $both],
                'staging' => [$both, $onlyB],
                default => [],
            },
        );

        $controller = new ReflectionClass(IssueController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(IssueController::class, 'issueSearchRepository')->setValue($controller, $issues);

        $method = new ReflectionMethod(IssueController::class, 'buildEnvironmentCompare');
        $result = $method->invoke($controller, $project, 'prod', 'staging');

        self::assertSame('prod', $result['environmentA']);
        self::assertSame('staging', $result['environmentB']);
        self::assertSame([$onlyA], $result['onlyA']);
        self::assertSame([$onlyB], $result['onlyB']);
        self::assertSame([$both], $result['both']);
    }

    private function issue(Project $project, int $id, string $title): Issue
    {
        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp-'.$id)
            ->setTitle($title)
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, $id);

        return $issue;
    }
}
