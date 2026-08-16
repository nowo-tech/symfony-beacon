<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Request;

final class IssueErrorSurfacesFunctionalTest extends DatabaseWebTestCase
{
    public function testConsoleHttpMessengerAndTraceAppearOnIssueShow(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('error-surfaces@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'error-surfaces-ui'));
        $issue->setTitle('Rich error surfaces');
        $issue->setCulprit('secrets:reveal');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId(bin2hex(random_bytes(8)));
        $event->setReceivedAt(new DateTimeImmutable());
        $event->setEventTimestamp(new DateTimeImmutable());
        $event->setPayload([
            'level' => 'error',
            'platform' => 'php',
            'tags' => ['trace_id' => 'abcdef0123456789'],
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'Not enough arguments (missing: "name").',
                    'stacktrace' => ['frames' => []],
                ]],
            ],
            'extra' => [
                'trace_id' => 'abcdef0123456789',
                'http' => [
                    'route' => 'issue_show',
                    'controller' => 'App\\Issues\\Controller\\IssueController::show',
                    'status_code' => 500,
                    'query_keys' => ['status', 'q'],
                ],
                'console' => [
                    'command' => 'secrets:reveal',
                    'command_class' => Command::class,
                    'exit_code' => 1,
                    'php_sapi' => 'cli',
                    'interactive' => false,
                    'verbosity' => 32,
                    'cwd' => '/app',
                    'missing_arguments' => ['name'],
                    'arguments' => ['name' => null],
                    'options' => ['env' => 'dev', 'password' => '[Filtered]'],
                ],
                'messenger' => [
                    'message_class' => 'App\\Message\\DemoMessage',
                    'handler_class' => 'App\\MessageHandler\\DemoHandler',
                    'receiver_name' => 'async',
                    'transport' => 'async',
                    'retry_count' => 2,
                    'first_failure_at' => '2026-08-16T00:00:00+00:00',
                ],
                'scheduler' => [
                    'schedule_name' => 'default',
                    'recurring_id' => 'cleanup',
                    'trigger' => '0 * * * *',
                ],
                'monolog' => ['channel' => 'app', 'level' => 'ERROR'],
                'fatal' => ['file' => '/app/src/Boom.php', 'line' => 12, 'type' => 1],
            ],
        ]);

        $em->persist($issue);
        $em->persist($event);
        $em->flush();

        $this->login($client, $owner);
        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="issue-console-command"]', 'secrets:reveal');
        self::assertSelectorTextContains('[data-testid="issue-console-missing"]', 'name');
        self::assertSelectorTextContains('[data-testid="issue-console-arguments"]', 'name');
        self::assertSelectorTextContains('[data-testid="issue-messenger-message"]', 'App\\Message\\DemoMessage');
        self::assertSelectorTextContains('[data-testid="issue-scheduler"]', 'default');
        self::assertSelectorTextContains('[data-testid="issue-monolog"]', 'app');
        self::assertSelectorTextContains('[data-testid="issue-fatal"]', 'Boom.php');
        self::assertSelectorTextContains('[data-testid="issue-trace-id"]', 'abcdef0123456789');
        self::assertSelectorTextContains('[data-testid="issue-http-route"]', 'issue_show');
        self::assertSelectorTextContains('.issue-tags:not(.issue-tags--custom)', 'trace_id');
        self::assertSelectorTextContains('.issue-tags:not(.issue-tags--custom)', 'console.command');
        self::assertSelectorTextContains('.issue-tags:not(.issue-tags--custom)', 'messenger.handler');
    }
}
