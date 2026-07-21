<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\MessageHandler\ProcessEnvelopeHandler;
use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Shared\IssueStatus;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotificationOnIngestTest extends DatabaseWebTestCase
{
    public function testNewIssueAndIgnoredRegressionSendHttpNotifications(): void
    {
        [, , $project] = $this->bootWithDemoProject('ingest-notif@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('HTTP');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $requests = [];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse('ok', ['http_code' => 200]);
        });
        self::getContainer()->set(HttpClientInterface::class, $mock);

        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('evt-1', 'TypeError: boom 1'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertStringContainsString('issue.new', (string) $requests[0]['body']);

        $issue = $em->getRepository(Issue::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(Issue::class, $issue);
        $issue->setStatus(IssueStatus::Ignored);
        $em->flush();

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('evt-2', 'TypeError: boom 2'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        self::assertCount(2, $requests);
        self::assertStringContainsString('issue.regression', (string) $requests[1]['body']);

        $em->refresh($issue);
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());
    }

    public function testDuplicateOpenIssueDoesNotNotify(): void
    {
        [, , $project] = $this->bootWithDemoProject('ingest-dup@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('HTTP');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $requests = [];
        $mock = new MockHttpClient(static function () use (&$requests): MockResponse {
            $requests[] = true;

            return new MockResponse('ok', ['http_code' => 200]);
        });
        self::getContainer()->set(HttpClientInterface::class, $mock);

        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('dup-1', 'SameError: id=1'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));
        self::assertCount(1, $requests);

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('dup-2', 'SameError: id=2'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));
        self::assertCount(1, $requests);
    }

    private function eventEnvelope(string $eventId, string $value): string
    {
        $header = json_encode(['dsn' => 'https://x@localhost/1'], \JSON_THROW_ON_ERROR);
        $item = json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR);
        $type = explode(':', $value, 2)[0];
        $payload = json_encode([
            'event_id' => $eventId,
            'level' => 'error',
            'exception' => [
                'values' => [[
                    'type' => $type,
                    'value' => $value,
                    'stacktrace' => [
                        'frames' => [[
                            'filename' => 'src/App.php',
                            'function' => 'run',
                            'in_app' => true,
                        ]],
                    ],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        return $header."\n".$item."\n".$payload."\n";
    }
}
