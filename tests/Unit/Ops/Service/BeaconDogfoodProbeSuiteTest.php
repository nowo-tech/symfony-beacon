<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Service;

use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Ops\Service\BeaconDogfoodProbeCaseResult;
use App\Ops\Service\BeaconDogfoodProbeSuite;
use App\Ops\Service\BeaconDogfoodProbeSuiteReport;
use Nowo\BeaconBundle\Dsn\BeaconDsn;
use Nowo\BeaconBundle\Dsn\BeaconDsnParser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BeaconDogfoodProbeSuiteTest extends TestCase
{
    public function testKindsCoverPlannedSuite(): void
    {
        self::assertCount(10, BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('console', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('http', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('messenger', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('breadcrumbs', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('db-sql', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('db-connection', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('long-content', BeaconDogfoodProbeSuite::KINDS);
    }

    public function testConsoleCaseIncludesCommandSnapshot(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('console', 'tok123');

        self::assertSame('error', $spec['level']);
        self::assertNotNull($spec['throwable']);
        self::assertSame(['beacon-suite', 'console', 'tok123'], $spec['fingerprint']);
        self::assertIsArray($spec['extra']['console'] ?? null);
        $console = $spec['extra']['console'];
        self::assertSame('app:beacon:test', $console['command']);
        self::assertArrayHasKey('command_class', $console);
        self::assertArrayHasKey('arguments', $console);
        self::assertArrayHasKey('options', $console);
        self::assertTrue($console['options']['suite'] ?? false);
    }

    public function testHttpCaseIncludesRouteAndStatus(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('http', 'tok456');

        self::assertNotNull($spec['request']);
        self::assertIsArray($spec['extra']['http'] ?? null);
        $http = $spec['extra']['http'];
        self::assertSame('project_issues_index', $http['route']);
        self::assertSame(500, $http['status_code']);
        self::assertSame(['q'], $http['query_keys']);
    }

    public function testMessengerCaseIncludesScheduler(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('messenger', 'tok789');

        self::assertIsArray($spec['extra']['messenger'] ?? null);
        self::assertIsArray($spec['extra']['scheduler'] ?? null);
        self::assertSame('async_notify', $spec['extra']['messenger']['transport']);
        self::assertStringContainsString('tok789', (string) $spec['extra']['scheduler']['recurring_id']);
    }

    public function testFingerprintsDifferPerKindAndRunToken(): void
    {
        $suite = $this->suite();
        $a = $suite->caseSpec('exception', 'run-a')['fingerprint'];
        $b = $suite->caseSpec('console', 'run-a')['fingerprint'];
        $c = $suite->caseSpec('exception', 'run-b')['fingerprint'];

        self::assertNotSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertSame('run-a', $a[2]);
        self::assertSame('run-b', $c[2]);
    }

    public function testBuildEnvelopeBodyAttachesTagsBreadcrumbsAndConsole(): void
    {
        $suite = $this->suite();
        $dsn = new BeaconDsn('http', 'publickey', 'secretkey', '127.0.0.1', null, '01a005d2-c7da-7ee0-8e37-3d0ec353306c');

        $consoleBody = $suite->buildEnvelopeBody('console', 'ab12cd34', $dsn);
        $consolePayload = $this->decodePayload($consoleBody);
        self::assertSame('dogfood.suite', $consolePayload['tags']['source'] ?? null);
        self::assertSame('console', $consolePayload['tags']['probe_kind'] ?? null);
        self::assertSame('ab12cd34', $consolePayload['tags']['probe_run'] ?? null);
        self::assertSame('app:beacon:test', $consolePayload['tags']['console.command'] ?? null);
        self::assertSame('cli://app:beacon:test', $consolePayload['tags']['transaction'] ?? null);
        self::assertSame('app:beacon:test', $consolePayload['extra']['console']['command'] ?? null);
        self::assertArrayHasKey('exception', $consolePayload);

        $httpBody = $suite->buildEnvelopeBody('http', 'ab12cd34', $dsn);
        $httpPayload = $this->decodePayload($httpBody);
        self::assertSame('project_issues_index', $httpPayload['tags']['http.route'] ?? null);
        self::assertNotEmpty($httpPayload['tags']['url'] ?? null);
        self::assertSame('GET', $httpPayload['tags']['http.method'] ?? null);

        $crumbsBody = $suite->buildEnvelopeBody('breadcrumbs', 'ab12cd34', $dsn);
        $crumbsPayload = $this->decodePayload($crumbsBody);
        self::assertSame('warning', $crumbsPayload['level'] ?? null);
        $values = $crumbsPayload['breadcrumbs']['values'] ?? [];
        self::assertIsArray($values);
        self::assertCount(3, $values);
    }

    public function testDbSqlCasePopulatesContextsDbAndTags(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('db-sql', 'dbsql01');
        self::assertSame('error', $spec['level']);
        self::assertNotNull($spec['throwable']);
        self::assertSame('unknown_column', $spec['extra']['db']['scenario'] ?? null);
        self::assertCount(2, $spec['breadcrumbs']);

        $payload = $this->decodePayload($suite->buildEnvelopeBody('db-sql', 'dbsql01'));
        self::assertSame('42S22', $payload['contexts']['db']['sqlstate'] ?? null);
        self::assertSame('1054', $payload['contexts']['db']['code'] ?? null);
        self::assertStringContainsString('nonexistent_col', (string) ($payload['contexts']['db']['sql'] ?? ''));
        self::assertSame('unknown_column', $payload['tags']['db.scenario'] ?? null);
        self::assertSame('cli://app:beacon:test#db-sql', $payload['tags']['transaction'] ?? null);
        self::assertArrayHasKey('exception', $payload);
    }

    public function testDbConnectionCasePopulatesContextsDb(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('db-connection', 'dbconn1');
        self::assertSame('connection_refused', $spec['extra']['db']['scenario'] ?? null);

        $payload = $this->decodePayload($suite->buildEnvelopeBody('db-connection', 'dbconn1'));
        self::assertSame('HY000', $payload['contexts']['db']['sqlstate'] ?? null);
        self::assertSame('2002', $payload['contexts']['db']['code'] ?? null);
        self::assertSame('connection_refused', $payload['tags']['db.scenario'] ?? null);
        self::assertStringContainsString('Connection refused', (string) ($payload['exception']['values'][0]['value'] ?? ''));
    }

    public function testLongContentCaseExceedsSqlScrubLimitAndCarriesDump(): void
    {
        $suite = $this->suite();
        $spec = $suite->caseSpec('long-content', 'long01');
        self::assertSame('long_sql_and_extra', $spec['extra']['db']['scenario'] ?? null);
        self::assertGreaterThanOrEqual(
            BeaconDogfoodProbeSuite::LONG_CONTENT_EXTRA_BYTES,
            \strlen((string) ($spec['extra']['dump'] ?? '')),
        );

        $payload = $this->decodePayload($suite->buildEnvelopeBody('long-content', 'long01'));
        $sql = (string) ($payload['contexts']['db']['sql'] ?? '');
        self::assertNotSame('', $sql);
        self::assertLessThanOrEqual(\Nowo\BeaconBundle\Context\DatabaseExceptionContext::MAX_SQL_LENGTH, mb_strlen($sql));
        self::assertGreaterThanOrEqual(8000, mb_strlen($sql));
        self::assertSame('long_sql_and_extra', $payload['tags']['db.scenario'] ?? null);
        self::assertGreaterThanOrEqual(
            BeaconDogfoodProbeSuite::LONG_CONTENT_EXTRA_BYTES,
            \strlen((string) ($payload['extra']['dump'] ?? '')),
        );
        self::assertGreaterThan(400, mb_strlen((string) ($payload['message'] ?? '')));
    }

    public function testPreviewFailsOnEmptyDsn(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            '',
            false,
        );
        $report = $suite->preview('fixed');
        self::assertFalse($report->success);
        self::assertNotNull($report->errorMessage);
        self::assertSame(BeaconDogfoodProbeSuite::KINDS, $report->plannedKinds);
    }

    public function testRunSendsAllKindsAndPrefersConsoleForDiagnostics(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{"id":"ok"}', ['http_code' => 200]);
        });

        $dsn = 'http://publickey:secretkey@127.0.0.1/01a005d2-c7da-7ee0-8e37-3d0ec353306c';
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            $client,
            $dsn,
            true,
            false,
            2.0,
            'test',
            '',
            'phpunit',
        );

        $report = $suite->run('diag01');
        self::assertTrue($report->success);
        self::assertSame(\count(BeaconDogfoodProbeSuite::KINDS), $calls);
        self::assertCount(\count(BeaconDogfoodProbeSuite::KINDS), $report->cases);
        self::assertSame('diag01', $report->runToken);

        $consoleCase = null;
        foreach ($report->cases as $case) {
            self::assertTrue($case->accepted);
            self::assertNotNull($case->eventId);
            if ('console' === $case->kind) {
                $consoleCase = $case;
            }
        }
        self::assertNotNull($consoleCase);
        self::assertSame($consoleCase->eventId, $report->diagnosticEventId());
    }

    public function testPreviewSucceedsWithValidDsnAndTruncatesPublicKey(): void
    {
        $report = $this->suite()->preview('preview01');
        self::assertTrue($report->success);
        self::assertSame('preview01', $report->runToken);
        self::assertSame(BeaconDogfoodProbeSuite::KINDS, $report->plannedKinds);
        self::assertSame([], $report->cases);
        self::assertSame('http://127.0.0.1', $report->target['origin'] ?? null);
        self::assertSame('publicke…', $report->target['public_key'] ?? null);
        self::assertTrue($report->target['reporting_enabled'] ?? false);
    }

    public function testPreviewKeepsShortPublicKeyAndGeneratesRunToken(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            'http://pk:sk@127.0.0.1/01a005d2-c7da-7ee0-8e37-3d0ec353306c',
            true,
        );
        $report = $suite->preview('   ');
        self::assertTrue($report->success);
        self::assertSame('pk', $report->target['public_key'] ?? null);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $report->runToken);
    }

    public function testPreviewFailsWhenDsnIsUnresolvedParameter(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            '%env(BEACON_DSN)%',
            false,
        );
        $report = $suite->preview('param');
        self::assertFalse($report->success);
        self::assertNotNull($report->errorMessage);
        self::assertStringContainsString('empty', $report->errorMessage);
    }

    public function testPreviewAndRunFailOnInvalidDsn(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            'not-a-dsn',
            true,
        );
        $preview = $suite->preview('bad');
        self::assertFalse($preview->success);
        self::assertNotNull($preview->errorMessage);
        self::assertStringContainsString('Invalid Beacon DSN', $preview->errorMessage);

        $run = $suite->run('bad');
        self::assertFalse($run->success);
        self::assertSame([], $run->cases);
        self::assertNotNull($run->errorMessage);
    }

    public function testRunReportsRejectedIngest(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('nope', ['http_code' => 400]));
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            $client,
            'http://publickey:secretkey@127.0.0.1/01a005d2-c7da-7ee0-8e37-3d0ec353306c',
            true,
            false,
            2.0,
            'test',
        );
        $report = $suite->run('reject1');
        self::assertFalse($report->success);
        self::assertNotNull($report->errorMessage);
        self::assertCount(\count(BeaconDogfoodProbeSuite::KINDS), $report->cases);
        foreach ($report->cases as $case) {
            self::assertFalse($case->accepted);
            self::assertSame(400, $case->httpStatus);
        }
        self::assertNull($report->diagnosticEventId());
    }

    public function testBuildEnvelopeBodyAndCaseSpecRejectUnknownKind(): void
    {
        $suite = $this->suite();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown dogfood probe kind');
        $suite->caseSpec('not-a-kind', 'tok');
    }

    public function testBuildEnvelopeBodyRejectsUnknownKind(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown dogfood probe kind');
        $this->suite()->buildEnvelopeBody('not-a-kind', 'tok');
    }

    public function testBuildEnvelopeBodyParsesConfiguredDsnAndCoversRemainingKinds(): void
    {
        $suite = $this->suite();
        $messenger = $this->decodePayload($suite->buildEnvelopeBody('messenger', 'tokm'));
        self::assertSame(DeliverWebPushForProjectMessage::class, $messenger['tags']['messenger.message_class'] ?? null);
        self::assertSame('messenger://'.DeliverWebPushForProjectMessage::class, $messenger['tags']['transaction'] ?? null);

        $exception = $this->decodePayload($suite->buildEnvelopeBody('exception', 'toke'));
        self::assertSame('cli://app:beacon:test#exception', $exception['tags']['transaction'] ?? null);

        $info = $this->decodePayload($suite->buildEnvelopeBody('message-info', 'toki'));
        self::assertSame('cli://app:beacon:test#message-info', $info['tags']['transaction'] ?? null);

        $error = $this->decodePayload($suite->buildEnvelopeBody('message-error', 'tokerr'));
        self::assertSame('cli://app:beacon:test#message-error', $error['tags']['transaction'] ?? null);

        $dbSql = $this->decodePayload($suite->buildEnvelopeBody('db-sql', 'tokdb'));
        self::assertSame('cli://app:beacon:test#db-sql', $dbSql['tags']['transaction'] ?? null);
    }

    public function testBuildEnvelopeBodyThrowsWhenConfiguredDsnIsEmpty(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            '',
            false,
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beacon DSN is empty');
        $suite->buildEnvelopeBody('console', 'tok');
    }

    public function testExtractEventIdHandlesMalformedEnvelopeHeaders(): void
    {
        $method = new ReflectionMethod(BeaconDogfoodProbeSuite::class, 'extractEventId');
        $suite = $this->suite();

        self::assertNull($method->invoke($suite, ''));
        self::assertNull($method->invoke($suite, 'not-json'));
        self::assertNull($method->invoke($suite, '{"event_id":123}'."\n".'{}'));
        self::assertSame('abc', $method->invoke($suite, '{"event_id":"abc"}'."\n".'{}'));
    }

    public function testClientTagsForKindFallsBackForUnknownKindAndBlankMessengerClass(): void
    {
        $method = new ReflectionMethod(BeaconDogfoodProbeSuite::class, 'clientTagsForKind');
        $suite = $this->suite();
        $emptySpec = [
            'message' => '',
            'level' => 'info',
            'throwable' => null,
            'extra' => [],
            'fingerprint' => [],
            'breadcrumbs' => [],
            'request' => null,
        ];

        $unknown = $method->invoke($suite, 'not-a-kind', 'tok', $emptySpec);
        self::assertSame('not-a-kind', $unknown['probe_kind']);
        self::assertArrayNotHasKey('transaction', $unknown);

        $blankMessenger = $method->invoke($suite, 'messenger', 'tok', [
            ...$emptySpec,
            'extra' => ['messenger' => ['message_class' => '']],
        ]);
        self::assertSame('unknown', $blankMessenger['messenger.message_class'] ?? null);
        self::assertSame('messenger://unknown', $blankMessenger['transaction'] ?? null);

        $httpFallback = $method->invoke($suite, 'http', 'tok', $emptySpec);
        self::assertSame('', $httpFallback['url'] ?? null);
        self::assertSame('project_issues_index', $httpFallback['http.route'] ?? null);
        self::assertSame('GET', $httpFallback['http.method'] ?? null);
        self::assertSame('project_issues_index', $httpFallback['transaction'] ?? null);
    }

    public function testPreviewTruncatesLongRunTokenAndKeepsReleaseOnEnvelope(): void
    {
        $suite = new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            'http://publickey:secretkey@127.0.0.1/01a005d2-c7da-7ee0-8e37-3d0ec353306c',
            true,
            true,
            5.0,
            'test',
            'v1.23.0-ci',
            'phpunit',
            new NullLogger(),
        );
        $longToken = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $report = $suite->preview($longToken);
        self::assertTrue($report->success);
        self::assertSame(substr($longToken, 0, 32), $report->runToken);

        $payload = $this->decodePayload($suite->buildEnvelopeBody('message-info', 'rel01'));
        self::assertSame('v1.23.0-ci', $payload['release'] ?? null);
    }

    public function testDiagnosticEventIdPrefersConsoleThenReturnsNull(): void
    {
        $empty = new BeaconDogfoodProbeSuiteReport('t', [], [], true);
        self::assertNull($empty->diagnosticEventId());

        $skipped = new BeaconDogfoodProbeSuiteReport('t', [], [
            new BeaconDogfoodProbeCaseResult('console', false, 'evt-console', 400, 'nope'),
            new BeaconDogfoodProbeCaseResult('exception', true, '', 200),
            new BeaconDogfoodProbeCaseResult('http', true, 'evt-http', 200),
        ], true);
        self::assertSame('evt-http', $skipped->diagnosticEventId());
    }

    private function suite(): BeaconDogfoodProbeSuite
    {
        return new BeaconDogfoodProbeSuite(
            new BeaconDsnParser(),
            new MockHttpClient(),
            'http://publickey:secretkey@127.0.0.1/01a005d2-c7da-7ee0-8e37-3d0ec353306c',
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $envelopeBody): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($envelopeBody)) ?: [];
        self::assertGreaterThanOrEqual(3, \count($lines));
        $payload = json_decode($lines[2], true);
        self::assertIsArray($payload);

        return $payload;
    }
}
