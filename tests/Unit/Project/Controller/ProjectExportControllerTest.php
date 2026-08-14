<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\IssueJsonNormalizer;
use App\Project\Controller\ProjectExportController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectExportControllerTest extends TestCase
{
    public function testCsvSafeCellNeutralizesFormulaInjection(): void
    {
        $controller = $this->controller();
        $cell = new ReflectionMethod(ProjectExportController::class, 'csvSafeCell');
        $row = new ReflectionMethod(ProjectExportController::class, 'csvSafeRow');

        self::assertSame('safe', $cell->invoke($controller, 'safe'));
        self::assertSame("'=CMD", $cell->invoke($controller, '=CMD'));
        self::assertSame("'+1", $cell->invoke($controller, '+1'));
        self::assertSame("'-1", $cell->invoke($controller, '-1'));
        self::assertSame("'@mention", $cell->invoke($controller, '@mention'));
        self::assertSame(['ok', "'=hack"], $row->invoke($controller, ['ok', '=hack']));
    }

    public function testEventToArrayIncludesIssueAndTimestamps(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('Boom')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
        $received = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $eventTs = new DateTimeImmutable('2026-01-02T03:00:00+00:00');
        $event = new Event()
            ->setProject($project)
            ->setIssue($issue)
            ->setEventId('evt-1')
            ->setEnvironment('prod')
            ->setReleaseVersion('1.0.0')
            ->setPlatform('php')
            ->setPayload([])
            ->setReceivedAt($received)
            ->setEventTimestamp($eventTs);

        $method = new ReflectionMethod(ProjectExportController::class, 'eventToArray');
        $row = $method->invoke($this->controller(), $event);

        self::assertSame('evt-1', $row['event_id']);
        self::assertSame($issue->getUuid(), $row['issue_uuid']);
        self::assertSame('Boom', $row['issue_title']);
        self::assertSame(IssueLevel::Error->value, $row['issue_level']);
        self::assertSame(IssueStatus::Unresolved->value, $row['issue_status']);
        self::assertSame('prod', $row['environment']);
        self::assertSame('1.0.0', $row['release']);
        self::assertSame('php', $row['platform']);
        self::assertSame($received->format(\DATE_ATOM), $row['received_at']);
        self::assertSame($eventTs->format(\DATE_ATOM), $row['event_timestamp']);
    }

    public function testExportIssuesJsonReturnsEmptyPayload(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('search')->willReturn([]);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([]);

        $controller = new ProjectExportController(
            $issues,
            $this->createStub(EventRepository::class),
            $memberships,
            new IssueJsonNormalizer(),
        );

        $response = $controller->exportIssues($project, Request::create('/export'), 'json');
        self::assertInstanceOf(JsonResponse::class, $response);
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa', $payload['project']['uuid']);
        self::assertSame('acme', $payload['project']['slug']);
        self::assertSame(ProjectExportController::EXPORT_LIMIT, $payload['limit']);
        self::assertSame(0, $payload['count']);
        self::assertSame([], $payload['issues']);
    }

    public function testCsvStreamWritesBomHeadersAndRows(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(ProjectExportController::class, 'csvStream');
        /** @var StreamedResponse $response */
        $response = $method->invoke(
            $controller,
            'demo.csv',
            ['a', 'b'],
            static function (): iterable {
                yield ['1', '=x'];
            },
        );

        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('demo.csv', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
        self::assertStringStartsWith("\xEF\xBB\xBF", $body);
        self::assertStringContainsString('a,b', $body);
        self::assertStringContainsString("'=x", $body);
    }

    private function controller(): ProjectExportController
    {
        return new ProjectExportController(
            $this->createStub(IssueSearchRepository::class),
            $this->createStub(EventRepository::class),
            $this->createStub(ProjectMembershipRepository::class),
            new IssueJsonNormalizer(),
        );
    }
}
