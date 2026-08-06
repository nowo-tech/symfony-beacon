<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Enum\IssueLevel;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationPayloadBuilderTest extends TestCase
{
    private function builder(): NotificationPayloadBuilder
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://beacon.test/'.$route.'/'.($params['id'] ?? $params['projectId'] ?? ''),
        );

        return new NotificationPayloadBuilder($urls);
    }

    private function project(): Project
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        return $project;
    }

    private function issue(Project $project): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(str_repeat('a', 32));
        $issue->setTitle('Boom');
        $issue->setCulprit('App\\Fail::run');
        $issue->setLevel(IssueLevel::Error);

        return $issue;
    }

    public function testIssueLifecyclePayloads(): void
    {
        $builder = $this->builder();
        $project = $this->project();
        $issue = $this->issue($project);
        $assignee = new User();
        $assignee->setEmail('a@example.com');
        $assignee->setDisplayName('Ada');

        $new = $builder->forNewIssue($project, $issue);
        self::assertSame('issue.new', $new['event']);
        self::assertSame('error', $new['category']);
        self::assertStringContainsString('Boom', (string) $new['summary']);

        $resolved = $builder->forIssueResolved($project, $issue);
        self::assertSame(NotificationCategories::ISSUE_RESOLVED, $resolved['event']);
        self::assertSame(NotificationCategories::ISSUE_RESOLVED, $resolved['category']);

        $assigned = $builder->forIssueAssigned($project, $issue, null, $assignee);
        self::assertNull($assigned['assignee']['previous']);
        self::assertSame('Ada', $assigned['assignee']['current']['display_name']);

        $comment = new IssueComment();
        $comment->setIssue($issue);
        $comment->setAuthor($assignee);
        $comment->setBody(str_repeat('x', 250));
        $commented = $builder->forIssueCommented($project, $issue, $comment);
        self::assertSame(200, mb_strlen((string) $commented['comment']['body_preview']));

        $canonical = $this->issue($project);
        $canonical->setTitle('Canonical');
        $dup = $builder->forIssueDuplicated($project, $issue, $canonical);
        self::assertSame('Canonical', $dup['canonical_issue']['title']);
    }

    public function testThresholdNPlusOneTestAndDigest(): void
    {
        $builder = $this->builder();
        $project = $this->project();

        $rule = new ProjectThresholdRule();
        $rule->setLabel('Spike');
        $rule->setErrorCount(10);
        $rule->setWindowMinutes(15);
        $threshold = $builder->forVolumeThreshold($project, $rule, 12);
        self::assertSame(NotificationCategories::VOLUME_THRESHOLD, $threshold['event']);
        self::assertStringStartsWith('Spike - ', (string) $threshold['summary']);
        self::assertSame(12, $threshold['count']);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setTransactionName('GET /api');
        $tx->setNPlusOneCount(3);
        $tx->setSpanCount(40);
        $n1 = $builder->forNPlusOne($project, $tx);
        self::assertSame(NotificationCategories::N_PLUS_ONE, $n1['category']);
        self::assertStringContainsString('GET /api', (string) $n1['summary']);

        $sample = $builder->forTest($project, 'Hook A', NotificationDestinationType::Slack);
        self::assertTrue($sample['test']);
        self::assertStringContainsString('Slack', (string) $sample['summary']);

        $digest = $builder->forDigest($project, 'Hook A', array_fill(0, 21, [
            'event' => 'issue.new',
            'summary' => 'One',
            'category' => 'error',
        ]));
        self::assertSame(21, $digest['held_count']);
        self::assertStringContainsString('and 1 more', (string) $digest['summary']);
    }
}
