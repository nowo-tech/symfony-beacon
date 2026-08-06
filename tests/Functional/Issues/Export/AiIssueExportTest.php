<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues\Export;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Export\AiIssueExportFormatter;
use App\Issues\Enum\IssueStatus;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class AiIssueExportTest extends DatabaseWebTestCase
{
    public function testMemberCanExportAiMarkdownAndJson(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('ai-export@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-ai-export');
        $issue->setTitle('AI export target');
        $issue->setCulprit('App\\Fail');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId('evt-ai-export-1');
        $event->setEnvironment('dev');
        $event->setPlatform('php');
        $event->setPayload([
            'exception' => [
                'values' => [[
                    'type' => 'LogicException',
                    'value' => 'exported',
                    'stacktrace' => [
                        'frames' => [
                            ['filename' => 'src/Fail.php', 'lineno' => 9, 'function' => 'run'],
                        ],
                    ],
                ]],
            ],
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.test/fail',
                'headers' => [
                    'Authorization' => 'Bearer should-not-leak',
                    'Content-Type' => 'application/json',
                ],
            ],
        ]);
        $em->persist($issue);
        $em->persist($event);
        $em->flush();

        $this->login($client, $owner);

        $mdUrl = '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/export/ai.md';
        $client->request(Request::METHOD_GET, $mdUrl);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/markdown', (string) $client->getResponse()->headers->get('Content-Type'));
        $md = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('format: '.AiIssueExportFormatter::FORMAT, $md);
        self::assertStringContainsString('LogicException', $md);
        self::assertStringNotContainsString('should-not-leak', $md);

        $jsonUrl = '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/export/ai.json?event=evt-ai-export-1';
        $client->request(Request::METHOD_GET, $jsonUrl);
        self::assertResponseIsSuccessful();
        /** @var array{format: string, event: array{event_id: string}} $json */
        $json = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(AiIssueExportFormatter::FORMAT, $json['format']);
        self::assertSame('evt-ai-export-1', $json['event']['event_id']);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/export/ai.json?event=missing-event',
        );
        self::assertResponseStatusCodeSame(404);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-testid="issue-ai-export"]')->count());
    }
}
