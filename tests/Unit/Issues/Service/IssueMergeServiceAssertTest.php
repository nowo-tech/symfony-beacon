<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueMergeServiceAssertTest extends TestCase
{
    private IssueMergeService $service;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $this->service = new IssueMergeService(
            $this->createStub(EventRepository::class),
            $this->createStub(IssueRepository::class),
            new IssueHistoryRecorder($em),
            $em,
        );
    }

    public function testRejectsSameIdOrUuid(): void
    {
        $project = $this->project(1);
        $issue = $this->issue(10, $project);

        try {
            $this->service->assertCanMarkAsDuplicate($issue, $issue);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame('cannot_merge_self', $e->getMessage());
        }

        $sameUuid = $this->issue(11, $project);
        (new ReflectionProperty($sameUuid, 'uuid'))->setValue($sameUuid, $issue->getUuid());

        try {
            $this->service->assertCanMarkAsDuplicate($issue, $sameUuid);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame('cannot_merge_self', $e->getMessage());
        }
    }

    public function testRejectsDifferentProjects(): void
    {
        $source = $this->issue(1, $this->project(1));
        $canonical = $this->issue(2, $this->project(2));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wrong_project');
        $this->service->assertCanMarkAsDuplicate($source, $canonical);
    }

    public function testRejectsCircularDuplicateChain(): void
    {
        $project = $this->project(1);
        $source = $this->issue(1, $project);
        $mid = $this->issue(2, $project);
        $canonical = $this->issue(3, $project);
        $mid->setDuplicateOf($source);
        $canonical->setDuplicateOf($mid);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('circular');
        $this->service->assertCanMarkAsDuplicate($source, $canonical);
    }

    public function testAllowsValidPair(): void
    {
        $project = $this->project(1);
        $source = $this->issue(1, $project);
        $canonical = $this->issue(2, $project);

        $this->service->assertCanMarkAsDuplicate($source, $canonical);
        self::assertTrue(true);
    }

    private function project(int $id): Project
    {
        $project = new Project();
        $project->setSlug('p'.$id);
        $project->setName('P'.$id);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function issue(int $id, Project $project): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-'.$id);
        $issue->setTitle('Issue '.$id);
        (new ReflectionProperty(Issue::class, 'id'))->setValue($issue, $id);

        return $issue;
    }
}
