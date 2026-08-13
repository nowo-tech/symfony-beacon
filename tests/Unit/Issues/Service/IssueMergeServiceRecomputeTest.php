<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Project\Entity\Project;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueMergeServiceRecomputeTest extends TestCase
{
    public function testRecomputeAggregatesNoOpWithoutIdAndAppliesSqlRow(): void
    {
        $service = $this->service($this->createStub(EntityManagerInterface::class));
        $service->recomputeAggregates(new Issue());

        $project = $this->project(1);
        $issue = $this->issue(10, $project);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql) {
            if (str_contains($sql, 'COUNT(*)')) {
                return [
                    'cnt' => 2,
                    'first_seen' => '2026-08-01 00:00:00',
                    'last_seen' => '2026-08-02 00:00:00',
                ];
            }

            return [
                'release_version' => '1.2.0',
                'environment' => 'production',
            ];
        });
        $connection->method('fetchOne')->willReturn('1.0.0');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $this->service($em)->recomputeAggregates($issue);
        self::assertSame(2, $issue->getEventCount());
        self::assertSame('1.0.0', $issue->getFirstRelease());
        self::assertSame('1.2.0', $issue->getLastRelease());
        self::assertSame('production', $issue->getLastEnvironment());
    }

    public function testRecomputeAggregatesForProjectUpdatesIssues(): void
    {
        $project = $this->project(5);
        $issue = $this->issue(20, $project);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static function (string $sql) use ($issue) {
            if (str_contains($sql, 'GROUP BY')) {
                return [[
                    'issue_id' => $issue->getId(),
                    'cnt' => 3,
                    'first_seen' => '2026-07-01 00:00:00',
                    'last_seen' => '2026-07-02 00:00:00',
                ]];
            }
            if (str_contains($sql, 'ORDER BY received_at ASC')) {
                return [['issue_id' => $issue->getId(), 'release_version' => '0.9.0']];
            }

            return [[
                'issue_id' => $issue->getId(),
                'release_version' => '1.1.0',
                'environment' => 'staging',
            ]];
        });

        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findBy')->willReturn([$issue]);

        $updated = $this->service($em, $issues)->recomputeAggregatesForProject($project);
        self::assertSame(1, $updated);
        self::assertSame(3, $issue->getEventCount());
        self::assertSame('0.9.0', $issue->getFirstRelease());
        self::assertSame('1.1.0', $issue->getLastRelease());
        self::assertSame('staging', $issue->getLastEnvironment());
        self::assertSame(1, $flush);
    }

    private function service(
        EntityManagerInterface $em,
        ?IssueRepository $issues = null,
    ): IssueMergeService {
        return new IssueMergeService(
            $this->createStub(EventRepository::class),
            $issues ?? $this->createStub(IssueRepository::class),
            new IssueHistoryRecorder($em),
            $em,
        );
    }

    private function project(int $id): Project
    {
        $project = (new Project())->setName('P')->setSlug('p'.$id);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function issue(int $id, Project $project): Issue
    {
        $issue = (new Issue())->setProject($project)->setTitle('I')->setStatus(IssueStatus::Unresolved);
        (new ReflectionProperty(Issue::class, 'id'))->setValue($issue, $id);

        return $issue;
    }
}
