<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Controller\ProjectReleaseHealthController;
use App\Project\Entity\Project;
use App\Shared\Form\GetFilterFormFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;

final class ProjectReleaseHealthControllerTest extends TestCase
{
    public function testNormalizeReleaseQueryDelegatesToIssueHelper(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(ProjectReleaseHealthController::class, 'normalizeReleaseQuery');

        self::assertNull($method->invoke($controller, null));
        self::assertNull($method->invoke($controller, '   '));
        self::assertSame(Issue::normalizeRelease(' 1.2.3 '), $method->invoke($controller, ' 1.2.3 '));
    }

    public function testBuildReleaseComparePartitionsIssues(): void
    {
        $project = (new Project())->setName('Acme')->setSlug('acme');
        $onlyA = $this->issue($project, 1, 'A-only');
        $both = $this->issue($project, 2, 'Both');
        $onlyB = $this->issue($project, 3, 'B-only');

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('findByRelease')->willReturnCallback(
            static function (Project $p, string $release) use ($onlyA, $both, $onlyB): array {
                return match ($release) {
                    '1.0.0' => [$onlyA, $both],
                    '2.0.0' => [$both, $onlyB],
                    default => [],
                };
            },
        );

        $controller = $this->controller($issues);
        $method = new ReflectionMethod(ProjectReleaseHealthController::class, 'buildReleaseCompare');
        $result = $method->invoke($controller, $project, '1.0.0', '2.0.0');

        self::assertSame('1.0.0', $result['releaseA']);
        self::assertSame('2.0.0', $result['releaseB']);
        self::assertSame([$onlyA], $result['onlyA']);
        self::assertSame([$onlyB], $result['onlyB']);
        self::assertSame([$both], $result['both']);
        self::assertSame(1, $result['onlyACount']);
        self::assertSame(1, $result['onlyBCount']);
        self::assertSame(1, $result['bothCount']);
    }

    private function controller(?IssueSearchRepository $issues = null): ProjectReleaseHealthController
    {
        return new ProjectReleaseHealthController(
            $issues ?? $this->createStub(IssueSearchRepository::class),
            $this->createStub(EventRepository::class),
            new GetFilterFormFactory($this->createStub(FormFactoryInterface::class)),
        );
    }

    private function issue(Project $project, int $id, string $title): Issue
    {
        $issue = (new Issue())
            ->setProject($project)
            ->setFingerprint('fp-'.$id)
            ->setTitle($title)
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
        (new ReflectionProperty(Issue::class, 'id'))->setValue($issue, $id);

        return $issue;
    }
}
