<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueMergeServiceMergeTest extends TestCase
{
    public function testMergeIntoCanonicalMovesEventsAndIgnoresSource(): void
    {
        $project = $this->project(1);
        $source = $this->issue(10, $project, 'Source');
        $canonical = $this->issue(11, $project, 'Canonical');
        $event = new Event()->setIssue($source)->setProject($project)->setEventId('e1')
            ->setReceivedAt(new DateTimeImmutable('2026-08-01T00:00:00+00:00'))
            ->setEventTimestamp(new DateTimeImmutable('2026-08-01T00:00:00+00:00'));

        $events = $this->createStub(EventRepository::class);
        $events->method('findBy')->willReturnCallback(static function (array $criteria) use ($source, $event): array {
            if (($criteria['issue'] ?? null) === $source) {
                return [$event, 'skip'];
            }

            return [];
        });

        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $service = new IssueMergeService(
            $events,
            $this->createStub(IssueRepository::class),
            new IssueHistoryRecorder($em),
            $em,
        );

        $moved = $service->mergeIntoCanonical($source, $canonical, $this->user(1));
        self::assertSame(1, $moved);
        self::assertSame($canonical, $event->getIssue());
        self::assertSame($canonical, $source->getDuplicateOf());
        self::assertSame(IssueStatus::Ignored, $source->getStatus());
        self::assertSame(0, $source->getEventCount());
        self::assertSame(1, $flush);
    }

    private function project(int $id): Project
    {
        $project = new Project()->setName('P')->setSlug('p');
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function issue(int $id, Project $project, string $title): Issue
    {
        $issue = new Issue()->setProject($project)->setTitle($title)->setStatus(IssueStatus::Unresolved)->setEventCount(2);
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, $id);

        return $issue;
    }

    private function user(int $id): User
    {
        $user = new User()->setEmail('u@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
