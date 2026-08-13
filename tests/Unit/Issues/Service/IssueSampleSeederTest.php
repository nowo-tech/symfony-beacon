<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueSampleSeeder;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueSampleSeederTest extends TestCase
{
    public function testZeroIssuesReturnsEmptyCounts(): void
    {
        $seeder = new IssueSampleSeeder(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(IssueRepository::class),
        );
        self::assertSame(['issues' => 0, 'events' => 0], $seeder->seed(new Project(), 0, 10));
    }

    public function testSeedsIssuesAndEventsSkippingExistingFingerprints(): void
    {
        $project = (new Project())->setName('P')->setSlug('p');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 42);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $existingFp = hash('sha256', 'beacon-sample|42|1');
        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findOneByProjectAndFingerprint')->willReturnCallback(
            static function (Project $p, string $fp) use ($existingFp): ?Issue {
                return $fp === $existingFp ? new Issue() : null;
            },
        );

        $result = new IssueSampleSeeder($em, $issues)->seed($project, 2, 3);
        self::assertSame(1, $result['issues']);
        self::assertSame(2, $result['events']);
        self::assertNotEmpty(array_filter($persisted, static fn (object $e): bool => $e instanceof Issue));
        self::assertNotEmpty(array_filter($persisted, static fn (object $e): bool => $e instanceof Event));
    }
}
