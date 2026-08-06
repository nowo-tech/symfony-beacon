<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Export;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Export\AiIssueExportFormatter;
use App\Project\Entity\Project;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use PHPUnit\Framework\TestCase;

final class AiIssueExportFormatterTest extends TestCase
{
    public function testMarkdownAndJsonIncludeFormatAndRedactSecrets(): void
    {
        $project = new Project();
        $project->setName('Symfony Beacon');
        $project->setSlug('symfony-beacon');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-ai-1');
        $issue->setTitle('Something broke');
        $issue->setCulprit('App\\Boom');
        $issue->setLevel(IssueLevel::Error);
        $issue->setStatus(IssueStatus::Unresolved);

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId('evt-ai-1');
        $event->setEnvironment('prod');
        $event->setReleaseVersion('1.0.0');
        $event->setPlatform('php');
        $event->setPayload([
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'nope',
                    'stacktrace' => [
                        'frames' => [
                            ['filename' => 'src/Boom.php', 'lineno' => 12, 'function' => 'explode'],
                        ],
                    ],
                ]],
            ],
            'request' => [
                'method' => 'GET',
                'url' => 'https://user:pass@example.test/oops?access_token=leak&ok=1',
                'headers' => [
                    'Authorization' => 'Bearer secret-token',
                    'Cookie' => 'session=abc',
                    'Accept' => 'text/html',
                ],
                'cookies' => ['session' => 'abc'],
                'data' => [
                    'password' => 'hunter2',
                    'ok' => '1',
                    'nested' => ['api_token' => 'nested-secret', 'safe' => 'yes'],
                ],
            ],
            'tags' => ['env' => 'prod', 'secret' => 'tag-secret'],
            'breadcrumbs' => [
                'values' => [
                    ['category' => 'http', 'message' => 'GET /oops'],
                    ['category' => 'auth', 'message' => 'Bearer abcdef.ghij'],
                ],
            ],
        ]);

        $formatter = new AiIssueExportFormatter();
        $data = $formatter->buildCanonical($project, $issue, $event, 'https://beacon.test/projects/p/issues/i');

        self::assertSame(AiIssueExportFormatter::FORMAT, $data['format']);
        self::assertSame('[redacted]', $data['request']['headers']['Authorization']);
        self::assertSame('[redacted]', $data['request']['headers']['Cookie']);
        self::assertSame('text/html', $data['request']['headers']['Accept']);
        self::assertSame('[redacted]', $data['request']['cookies']);
        self::assertSame('[redacted]', $data['request']['data']['password']);
        self::assertSame('1', $data['request']['data']['ok']);
        self::assertSame('[redacted]', $data['request']['data']['nested']['api_token']);
        self::assertSame('yes', $data['request']['data']['nested']['safe']);
        self::assertStringContainsString('[redacted]', (string) $data['request']['url']);
        self::assertStringNotContainsString('pass', (string) $data['request']['url']);
        self::assertStringNotContainsString('leak', (string) $data['request']['url']);
        self::assertSame('[redacted]', $data['tags']['secret']);
        self::assertSame('prod', $data['tags']['env']);
        self::assertSame('[redacted]', $data['breadcrumbs'][1]['message']);

        $md = $formatter->toMarkdown($data);
        self::assertStringContainsString('format: beacon-ai-export/v1', $md);
        self::assertStringContainsString('RuntimeException', $md);
        self::assertStringNotContainsString('Bearer secret-token', $md);
        self::assertStringNotContainsString('hunter2', $md);
        self::assertStringNotContainsString('tag-secret', $md);
        self::assertStringNotContainsString('nested-secret', $md);

        $json = $formatter->toJson($data);
        self::assertStringContainsString('"format": "beacon-ai-export/v1"', $json);
        self::assertStringNotContainsString('Bearer secret-token', $json);
    }
}
