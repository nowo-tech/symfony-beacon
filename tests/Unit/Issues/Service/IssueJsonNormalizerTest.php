<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueJsonNormalizer;
use App\Project\Entity\Project;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IssueJsonNormalizerTest extends TestCase
{
    public function testNormalizeIncludesCoreFieldsWithoutAssigneeOrDuplicate(): void
    {
        $project = new Project();
        $project->setName('Demo');
        $project->setSlug('demo');

        $firstSeen = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $lastSeen = new DateTimeImmutable('2026-01-02T11:00:00+00:00');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-1');
        $issue->setTitle('Boom');
        $issue->setCulprit('App\\Boom');
        $issue->setLevel(IssueLevel::Fatal);
        $issue->setStatus(IssueStatus::Resolved);
        $issue->setPriority(IssuePriority::High);
        $issue->setEventCount(7);
        $issue->setFirstSeen($firstSeen);
        $issue->setLastSeen($lastSeen);
        $issue->setFirstRelease('1.0.0');
        $issue->setLastRelease('1.0.1');
        $issue->setLastEnvironment('prod');

        $payload = new IssueJsonNormalizer()->normalize($issue);

        self::assertSame($issue->getUuid(), $payload['uuid']);
        self::assertSame('Boom', $payload['title']);
        self::assertSame(IssueLevel::Fatal->value, $payload['level']);
        self::assertSame(IssueStatus::Resolved->value, $payload['status']);
        self::assertSame(IssuePriority::High->value, $payload['priority']);
        self::assertSame('App\\Boom', $payload['culprit']);
        self::assertSame(7, $payload['event_count']);
        self::assertSame($firstSeen->format(\DATE_ATOM), $payload['first_seen']);
        self::assertSame($lastSeen->format(\DATE_ATOM), $payload['last_seen']);
        self::assertSame('1.0.0', $payload['first_release']);
        self::assertSame('1.0.1', $payload['last_release']);
        self::assertSame('prod', $payload['last_environment']);
        self::assertNull($payload['assignee_email']);
        self::assertNull($payload['duplicate_of_uuid']);
    }

    public function testNormalizeIncludesAssigneeAndDuplicate(): void
    {
        $assignee = new User();
        $assignee->setEmail('dev@example.com');

        $canonical = new Issue();
        $canonical->setFingerprint('fp-canonical');
        $canonical->setTitle('Canonical');

        $issue = new Issue();
        $issue->setFingerprint('fp-dup');
        $issue->setTitle('Dup');
        $issue->setAssignee($assignee);
        $issue->setDuplicateOf($canonical);

        $payload = new IssueJsonNormalizer()->normalize($issue);

        self::assertSame('dev@example.com', $payload['assignee_email']);
        self::assertSame($canonical->getUuid(), $payload['duplicate_of_uuid']);
    }
}
