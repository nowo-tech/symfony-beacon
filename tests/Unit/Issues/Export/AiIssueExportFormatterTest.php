<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Export;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Export\AiIssueExportFormatter;
use App\Project\Entity\Project;
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


    public function testBuildCanonicalUsesFallbacksForEmptyPayload(): void
    {
        $project = (new Project())
            ->setName('Beacon')
            ->setSlug('beacon');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-ai-2');
        $issue->setTitle('Fallback title');
        $issue->setCulprit('');
        $issue->setLevel(IssueLevel::Warning);
        $issue->setStatus(IssueStatus::Resolved);

        $formatter = new AiIssueExportFormatter();
        $data = $formatter->buildCanonical($project, $issue, null, 'https://beacon.test/issues/fallback');

        self::assertNull($data['event']);
        self::assertNull($data['exception']);
        self::assertSame([], $data['stacktrace']);
        self::assertNull($data['request']);
        self::assertSame([], $data['tags']);
        self::assertSame([], $data['breadcrumbs']);

        $md = $formatter->toMarkdown($data);
        self::assertStringContainsString('environment: —', $md);
        self::assertStringContainsString('_No exception payload._', $md);
        self::assertStringContainsString('_No frames._', $md);
        self::assertStringContainsString('_No request context._', $md);
        self::assertStringContainsString('_No tags._', $md);
        self::assertStringContainsString('_No breadcrumbs._', $md);
        self::assertStringContainsString('# Fallback title: Fallback title', $md);
    }

    public function testBuildCanonicalFallsBackToContextRequestAndTopLevelStacktrace(): void
    {
        $project = (new Project())
            ->setName('Beacon')
            ->setSlug('beacon');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-ai-3');
        $issue->setTitle('Context request');
        $issue->setCulprit('App\Worker');
        $issue->setLevel(IssueLevel::Error);
        $issue->setStatus(IssueStatus::Unresolved);

        $breadcrumbs = [];
        for ($i = 0; $i < 30; ++$i) {
            $breadcrumbs[] = [
                'category' => 'worker',
                'message' => 1 === $i ? 'api_key=secret-value' : 'step '.$i,
            ];
        }

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId('evt-ai-2');
        $event->setPayload([
            'message' => 'worker blew up',
            'stacktrace' => [
                'frames' => [
                    ['filename' => 'src/Worker.php', 'abs_path' => '/srv/app/src/Worker.php', 'lineno' => 44, 'function' => 'run', 'in_app' => true],
                    'ignored',
                ],
            ],
            'contexts' => [
                'request' => [
                    'method' => 'POST',
                    'path' => '/worker',
                    'headers' => ['X-Api-Key' => 'secret', 'Accept' => 'application/json'],
                    'data' => ['nested' => ['authorization' => 'Bearer abc'], 'safe' => 'ok'],
                    'query_string' => 'token=secret&ok=1',
                ],
            ],
            'tags' => [
                ['env', 'stage'],
                ['note', 'api-key: hidden'],
            ],
            'breadcrumbs' => $breadcrumbs,
        ]);

        $formatter = new AiIssueExportFormatter();
        $data = $formatter->buildCanonical($project, $issue, $event, 'https://beacon.test/issues/context');

        self::assertNull($data['exception']);
        self::assertSame('src/Worker.php', $data['stacktrace'][0]['filename']);
        self::assertSame('/srv/app/src/Worker.php', $data['stacktrace'][0]['abs_path']);
        self::assertSame('[redacted]', $data['request']['headers']['X-Api-Key']);
        self::assertSame('application/json', $data['request']['headers']['Accept']);
        self::assertSame('[redacted]', $data['request']['data']['nested']['authorization']);
        self::assertSame('ok', $data['request']['data']['safe']);
        self::assertStringContainsString('%5Bredacted%5D', $data['request']['query_string']);
        self::assertSame('stage', $data['tags']['env']);
        self::assertSame('[redacted]', $data['tags']['note']);
        self::assertCount(25, $data['breadcrumbs']);
        self::assertSame('[redacted]', $data['breadcrumbs'][1]['message']);

        $md = $formatter->toMarkdown($data);
        self::assertStringContainsString('- Message: worker blew up', $md);
        self::assertStringContainsString('src/Worker.php:44 run', $md);
        self::assertStringContainsString('- Method: POST', $md);
    }

    public function testBuildCanonicalHandlesMalformedAndRelativeRequestUrls(): void
    {
        $project = (new Project())
            ->setName('Beacon')
            ->setSlug('beacon');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-ai-4');
        $issue->setTitle('Malformed request');
        $issue->setCulprit('App\\Import');
        $issue->setLevel(IssueLevel::Info);
        $issue->setStatus(IssueStatus::Unresolved);

        $formatter = new AiIssueExportFormatter();

        $invalidUrlEvent = new Event();
        $invalidUrlEvent->setIssue($issue);
        $invalidUrlEvent->setPayload([
            'message' => ['not-a-string'],
            'request' => [
                'url' => 'http://example.com:99999',
                'query_string' => '',
            ],
            'tags' => 'not-an-array',
            'breadcrumbs' => ['ok', ['category' => 'job', 'message' => 'done']],
        ]);

        $invalidData = $formatter->buildCanonical($project, $issue, $invalidUrlEvent, 'https://beacon.test/issues/invalid');
        self::assertNull($invalidData['event']['message']);
        self::assertSame('http%3A%2F%2Fexample_com%3A99999=', $invalidData['request']['url']);
        self::assertSame('', $invalidData['request']['query_string']);
        self::assertSame([], $invalidData['tags']);
        self::assertCount(1, $invalidData['breadcrumbs']);

        $relativeUrlEvent = new Event();
        $relativeUrlEvent->setIssue($issue);
        $relativeUrlEvent->setPayload([
            'request' => [
                'url' => '/relative/path?token=secret',
                'query_string' => '&&',
            ],
        ]);

        $relativeData = $formatter->buildCanonical($project, $issue, $relativeUrlEvent, 'https://beacon.test/issues/relative');
        self::assertSame('%2Frelative%2Fpath%3Ftoken=%5Bredacted%5D', $relativeData['request']['url']);
        self::assertSame('&&', $relativeData['request']['query_string']);
    }

}
