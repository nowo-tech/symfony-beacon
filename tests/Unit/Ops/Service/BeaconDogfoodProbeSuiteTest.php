<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Service;

use App\Ops\Service\BeaconDogfoodProbeSuite;
use Nowo\BeaconBundle\Dsn\BeaconDsn;
use Nowo\BeaconBundle\Dsn\BeaconDsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BeaconDogfoodProbeSuiteTest extends TestCase
{
    public function testKindsCoverPlannedSuite(): void
    {
        self::assertCount(7, BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('console', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('http', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('messenger', BeaconDogfoodProbeSuite::KINDS);
        self::assertContains('breadcrumbs', BeaconDogfoodProbeSuite::KINDS);
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
        $client = new MockHttpClient(static function () use (&$calls) {
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
