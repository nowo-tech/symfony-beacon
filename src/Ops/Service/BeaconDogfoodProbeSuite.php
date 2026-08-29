<?php

declare(strict_types=1);

namespace App\Ops\Service;

use App\Issues\Controller\IssueController;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\MessageHandler\DeliverWebPushForProjectHandler;
use App\Ops\Command\BeaconTestCommand;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nowo\BeaconBundle\Breadcrumb\BreadcrumbBuffer;
use Nowo\BeaconBundle\Context\DatabaseExceptionContext;
use Nowo\BeaconBundle\Dsn\BeaconDsn;
use Nowo\BeaconBundle\Dsn\BeaconDsnParser;
use Nowo\BeaconBundle\Dsn\InvalidBeaconDsnException;
use Nowo\BeaconBundle\Envelope\EnvelopeBuilder;
use Nowo\BeaconBundle\Envelope\EnvelopeTransport;
use Nowo\BeaconBundle\Envelope\SendOptions;
use Nowo\BeaconBundle\Scope\Scope;
use PDOException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Posts several synthetic Envelopes so dogfood Issues exercise message / stack / console / HTTP / messenger / DB UI.
 *
 * Uses sync HTTP (same approach as BeaconBundle {@see \Nowo\BeaconBundle\Connection\BeaconConnectionTester})
 * regardless of `nowo_beacon.transport.mode`.
 */
final readonly class BeaconDogfoodProbeSuite
{
    /** Bytes of filler in the long-content probe (stays well under default 2 MiB envelope limit). */
    public const int LONG_CONTENT_EXTRA_BYTES = 48_000;

    /** Target SQL length so Query facts / contexts.db mark truncation (> {@see DatabaseExceptionContext::MAX_SQL_LENGTH}). */
    public const int LONG_SQL_TARGET_CHARS = 9_000;

    /** @var list<string> */
    public const array KINDS = [
        'message-info',
        'message-error',
        'exception',
        'console',
        'http',
        'messenger',
        'breadcrumbs',
        'db-sql',
        'db-connection',
        'long-content',
    ];

    public function __construct(
        private BeaconDsnParser $parser,
        private HttpClientInterface $httpClient,
        #[Autowire('%nowo.beacon.dsn%')]
        private string $dsn,
        #[Autowire('%nowo.beacon.enabled%')]
        private bool $reportingEnabled,
        #[Autowire('%nowo.beacon.verify_peer%')]
        private bool $verifyPeer = true,
        #[Autowire('%nowo.beacon.timeout%')]
        private float $timeout = 5.0,
        #[Autowire('%nowo.beacon.environment%')]
        private string $environment = 'prod',
        #[Autowire('%nowo.beacon.release%')]
        private string $release = '',
        #[Autowire('%nowo.beacon.server_name%')]
        private string $serverName = 'unknown',
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Validate DSN and list suite kinds without POSTing.
     */
    public function preview(?string $runToken = null): BeaconDogfoodProbeSuiteReport
    {
        $token = $this->normalizeRunToken($runToken);
        $parsed = $this->parseDsn();
        if (!$parsed['ok']) {
            return new BeaconDogfoodProbeSuiteReport(
                runToken: $token,
                target: [],
                cases: [],
                success: false,
                errorMessage: $parsed['error'],
                plannedKinds: self::KINDS,
            );
        }

        /** @var BeaconDsn $dsn */
        $dsn = $parsed['dsn'];

        return new BeaconDogfoodProbeSuiteReport(
            runToken: $token,
            target: $this->sanitizeTarget($dsn),
            cases: [],
            success: true,
            plannedKinds: self::KINDS,
        );
    }

    /**
     * POST every suite Envelope synchronously.
     */
    public function run(?string $runToken = null): BeaconDogfoodProbeSuiteReport
    {
        $token = $this->normalizeRunToken($runToken);
        $parsed = $this->parseDsn();
        if (!$parsed['ok']) {
            return new BeaconDogfoodProbeSuiteReport(
                runToken: $token,
                target: [],
                cases: [],
                success: false,
                errorMessage: $parsed['error'],
            );
        }

        /** @var BeaconDsn $dsn */
        $dsn = $parsed['dsn'];
        $target = $this->sanitizeTarget($dsn);
        $transport = new EnvelopeTransport(
            $this->httpClient,
            $dsn,
            $this->verifyPeer,
            $this->timeout,
            $this->logger ?? new NullLogger(),
        );

        $cases = [];
        $allAccepted = true;
        foreach (self::KINDS as $kind) {
            $body = $this->buildEnvelopeBody($kind, $token, $dsn);
            $eventId = $this->extractEventId($body);
            $result = $transport->sendDetailed($body);
            $accepted = $result->isAccepted();
            if (!$accepted) {
                $allAccepted = false;
            }
            $cases[] = new BeaconDogfoodProbeCaseResult(
                kind: $kind,
                accepted: $accepted,
                eventId: $eventId,
                httpStatus: $result->getStatusCode(),
                error: $accepted ? null : ($result->getErrorMessage() ?? 'ingest rejected'),
            );
        }

        return new BeaconDogfoodProbeSuiteReport(
            runToken: $token,
            target: $target,
            cases: $cases,
            success: $allAccepted,
            errorMessage: $allAccepted ? null : 'One or more suite probes were not accepted by ingest.',
        );
    }

    /**
     * Build one NDJSON Envelope for a suite kind (public for unit tests).
     */
    public function buildEnvelopeBody(string $kind, string $runToken, ?BeaconDsn $dsn = null): string
    {
        if (!\in_array($kind, self::KINDS, true)) {
            throw new RuntimeException('Unknown dogfood probe kind: '.$kind);
        }

        if (!$dsn instanceof BeaconDsn) {
            $parsed = $this->parseDsn();
            if (!$parsed['ok']) {
                throw new RuntimeException((string) $parsed['error']);
            }
            $dsn = $parsed['dsn'];
        }

        $spec = $this->caseSpec($kind, $runToken);
        $breadcrumbs = $spec['breadcrumbs'];
        $buffer = null;
        if ([] !== $breadcrumbs) {
            $buffer = new BreadcrumbBuffer();
            foreach ($breadcrumbs as $crumb) {
                $buffer->add($crumb['message'], $crumb['category'], $crumb['level']);
            }
        }

        $requestStack = null;
        if ($spec['request'] instanceof Request) {
            $requestStack = new RequestStack();
            $requestStack->push($spec['request']);
        }

        $scope = new Scope();
        $scope->setTags($this->clientTagsForKind($kind, $runToken, $spec));

        $release = '' !== $this->release ? $this->release : null;
        $builder = new EnvelopeBuilder(
            $this->environment,
            $release,
            $this->serverName,
            new SendOptions(),
            null,
            $buffer,
            $requestStack,
            5,
            $scope,
        );

        return $builder->buildEventEnvelope(
            $dsn,
            $spec['message'],
            $spec['level'],
            $spec['throwable'],
            $spec['extra'],
            $spec['fingerprint'],
        );
    }

    /**
     * Case definition used to build Envelopes (public for unit assertions).
     *
     * @return array{
     *     message: string,
     *     level: string,
     *     throwable: ?Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: ?Request
     * }
     */
    public function caseSpec(string $kind, string $runToken): array
    {
        if (!\in_array($kind, self::KINDS, true)) {
            throw new RuntimeException('Unknown dogfood probe kind: '.$kind);
        }

        $fingerprint = ['beacon-suite', $kind, $runToken];
        $label = 'Beacon dogfood suite ['.$kind.'] run='.$runToken;

        return match ($kind) {
            'message-info' => [
                'message' => $label,
                'level' => 'info',
                'throwable' => null,
                'extra' => ['source' => 'app:beacon:test', 'probe_kind' => $kind],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [],
                'request' => null,
            ],
            'message-error' => [
                'message' => $label,
                'level' => 'error',
                'throwable' => null,
                'extra' => ['source' => 'app:beacon:test', 'probe_kind' => $kind],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [],
                'request' => null,
            ],
            'exception' => [
                'message' => $label,
                'level' => 'error',
                'throwable' => new RuntimeException('Synthetic exception for dogfood suite ('.$runToken.')'),
                'extra' => ['source' => 'app:beacon:test', 'probe_kind' => $kind],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [],
                'request' => null,
            ],
            'console' => [
                'message' => $label,
                'level' => 'error',
                'throwable' => new RuntimeException('Synthetic console failure for dogfood suite ('.$runToken.')'),
                'extra' => [
                    'source' => 'app:beacon:test',
                    'probe_kind' => $kind,
                    'console' => [
                        'command' => 'app:beacon:test',
                        'command_class' => BeaconTestCommand::class,
                        'exit_code' => 1,
                        'php_sapi' => \PHP_SAPI,
                        'interactive' => false,
                        'verbosity' => 32,
                        'cwd' => getcwd() ?: '/app',
                        'arguments' => ['command' => 'app:beacon:test'],
                        'options' => ['suite' => true, 'wait' => '10'],
                    ],
                ],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [],
                'request' => null,
            ],
            'http' => $this->httpCaseSpec($label, $kind, $fingerprint, $runToken),
            'messenger' => [
                'message' => $label,
                'level' => 'error',
                'throwable' => new RuntimeException('Synthetic messenger failure for dogfood suite ('.$runToken.')'),
                'extra' => [
                    'source' => 'app:beacon:test',
                    'probe_kind' => $kind,
                    'messenger' => [
                        'message_class' => DeliverWebPushForProjectMessage::class,
                        'receiver_name' => 'async_notify',
                        'retry_count' => 2,
                        'bus' => 'messenger.bus.default',
                        'transport' => 'async_notify',
                        'handler_class' => DeliverWebPushForProjectHandler::class,
                    ],
                    'scheduler' => [
                        'schedule_name' => 'default',
                        'recurring_id' => 'dogfood-probe-'.$runToken,
                        'triggered_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
                        'trigger' => 'every 1 hour',
                    ],
                ],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [],
                'request' => null,
            ],
            'breadcrumbs' => [
                'message' => $label,
                'level' => 'warning',
                'throwable' => null,
                'extra' => ['source' => 'app:beacon:test', 'probe_kind' => $kind],
                'fingerprint' => $fingerprint,
                'breadcrumbs' => [
                    ['message' => 'Suite started', 'category' => 'dogfood', 'level' => 'info'],
                    ['message' => 'Building breadcrumbs probe', 'category' => 'dogfood', 'level' => 'info'],
                    ['message' => 'About to ingest warning message', 'category' => 'dogfood', 'level' => 'warning'],
                ],
                'request' => null,
            ],
            'db-sql' => $this->dbSqlCaseSpec($label, $kind, $fingerprint, $runToken),
            'db-connection' => $this->dbConnectionCaseSpec($label, $kind, $fingerprint, $runToken),
            'long-content' => $this->longContentCaseSpec($label, $kind, $fingerprint, $runToken),
        };
    }

    /**
     * @param list<string> $fingerprint
     *
     * @return array{
     *     message: string,
     *     level: string,
     *     throwable: Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: Request
     * }
     */
    private function httpCaseSpec(string $label, string $kind, array $fingerprint, string $runToken): array
    {
        $throwable = new HttpException(500, 'Synthetic HTTP failure for dogfood suite ('.$runToken.')');
        $request = Request::create(
            'https://beacon.example.test/projects/symfony-beacon/issues?q=dogfood-'.$runToken,
            Request::METHOD_GET,
        );
        $request->headers->set('User-Agent', 'BeaconDogfoodProbeSuite/1.0');
        $request->attributes->set('_route', 'project_issues_index');
        $request->attributes->set('_controller', IssueController::class.'::index');

        return [
            'message' => $label,
            'level' => 'error',
            'throwable' => $throwable,
            'extra' => [
                'source' => 'app:beacon:test',
                'probe_kind' => $kind,
                'request_uri' => $request->getUri(),
                'request_method' => $request->getMethod(),
                'http' => [
                    'route' => 'project_issues_index',
                    'controller' => IssueController::class.'::index',
                    'status_code' => 500,
                    'query_keys' => ['q'],
                    'client' => [
                        'ip' => '127.0.0.1',
                        'user_agent' => 'BeaconDogfoodProbeSuite/1.0',
                    ],
                ],
            ],
            'fingerprint' => $fingerprint,
            'breadcrumbs' => [],
            'request' => $request,
        ];
    }

    /**
     * Synthetic MySQL "unknown column" so Issue Query facts / contexts.db light up.
     *
     * @param list<string> $fingerprint
     *
     * @return array{
     *     message: string,
     *     level: string,
     *     throwable: Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: ?Request
     * }
     */
    private function dbSqlCaseSpec(string $label, string $kind, array $fingerprint, string $runToken): array
    {
        $sql = 'SELECT `nonexistent_col`, `id`, `title` FROM `issue` WHERE `project_id` = ? ORDER BY `last_seen` DESC LIMIT 25';
        $driverMessage = 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'nonexistent_col\' in \'field list\''
            .' (Connection: mysql, SQL: '.$sql.')';
        $throwable = $this->pdoWrappedException(
            'An exception occurred while executing a query: '.$driverMessage,
            '42S22',
            1054,
            'Unknown column \'nonexistent_col\' in \'field list\'',
        );

        return [
            'message' => $label,
            'level' => 'error',
            'throwable' => $throwable,
            'extra' => [
                'source' => 'app:beacon:test',
                'probe_kind' => $kind,
                'db' => [
                    'scenario' => 'unknown_column',
                    'run_token' => $runToken,
                ],
            ],
            'fingerprint' => $fingerprint,
            'breadcrumbs' => [
                ['message' => 'Opening Doctrine connection default', 'category' => 'db', 'level' => 'info'],
                ['message' => $sql, 'category' => 'db.query', 'level' => 'error'],
            ],
            'request' => null,
        ];
    }

    /**
     * Synthetic MySQL connection failure (refused / unreachable host).
     *
     * @param list<string> $fingerprint
     *
     * @return array{
     *     message: string,
     *     level: string,
     *     throwable: Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: ?Request
     * }
     */
    private function dbConnectionCaseSpec(string $label, string $kind, array $fingerprint, string $runToken): array
    {
        $driverMessage = 'SQLSTATE[HY000] [2002] Connection refused';
        $throwable = $this->pdoWrappedException(
            'An exception occurred in the driver: '.$driverMessage,
            'HY000',
            2002,
            'Connection refused',
        );

        return [
            'message' => $label,
            'level' => 'error',
            'throwable' => $throwable,
            'extra' => [
                'source' => 'app:beacon:test',
                'probe_kind' => $kind,
                'db' => [
                    'scenario' => 'connection_refused',
                    'host' => 'mysql-unreachable.invalid',
                    'port' => 3306,
                    'run_token' => $runToken,
                ],
            ],
            'fingerprint' => $fingerprint,
            'breadcrumbs' => [
                ['message' => 'Connecting to mysql-unreachable.invalid:3306', 'category' => 'db', 'level' => 'info'],
                ['message' => $driverMessage, 'category' => 'db', 'level' => 'error'],
            ],
            'request' => null,
        ];
    }

    /**
     * Oversized message / extra / SQL so UI truncation paths are exercised.
     *
     * @param list<string> $fingerprint
     *
     * @return array{
     *     message: string,
     *     level: string,
     *     throwable: Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: ?Request
     * }
     */
    private function longContentCaseSpec(string $label, string $kind, array $fingerprint, string $runToken): array
    {
        $longSql = $this->buildLongSelectSql(self::LONG_SQL_TARGET_CHARS);
        $driverMessage = 'SQLSTATE[42000]: Syntax error or access violation: 1110 Column \'col_1\' specified twice'
            .' (Connection: mysql, SQL: '.$longSql.')';
        $throwable = $this->pdoWrappedException(
            'An exception occurred while executing a query: '.$driverMessage,
            '42000',
            1110,
            'Column \'col_1\' specified twice',
        );
        $filler = str_repeat('████ long-content dogfood filler '.$runToken.' ', (int) ceil(self::LONG_CONTENT_EXTRA_BYTES / 40));
        $filler = substr($filler, 0, self::LONG_CONTENT_EXTRA_BYTES);

        return [
            'message' => $label.' '.str_repeat('▓', 512),
            'level' => 'error',
            'throwable' => $throwable,
            'extra' => [
                'source' => 'app:beacon:test',
                'probe_kind' => $kind,
                'db' => [
                    'scenario' => 'long_sql_and_extra',
                    'sql_chars' => mb_strlen($longSql),
                    'run_token' => $runToken,
                ],
                'dump' => $filler,
            ],
            'fingerprint' => $fingerprint,
            'breadcrumbs' => [
                ['message' => 'Building oversized dogfood payload', 'category' => 'dogfood', 'level' => 'info'],
                ['message' => substr($longSql, 0, 200).'…', 'category' => 'db.query', 'level' => 'error'],
            ],
            'request' => null,
        ];
    }

    /**
     * Doctrine-style wrapper around PDOException so EnvelopeBuilder fills contexts.db.
     */
    private function pdoWrappedException(string $outerMessage, string $sqlstate, int $vendorCode, string $driverMessage): Throwable
    {
        $pdo = new PDOException('SQLSTATE['.$sqlstate.'] ['.$vendorCode.'] '.$driverMessage, $vendorCode);
        $pdo->errorInfo = [$sqlstate, $vendorCode, $driverMessage];

        return new RuntimeException($outerMessage, $vendorCode, $pdo);
    }

    /**
     * Build a SELECT list long enough to exceed SQL scrub/truncation limits.
     */
    private function buildLongSelectSql(int $minChars): string
    {
        $parts = [];
        $i = 1;
        $sql = 'SELECT ';
        while (mb_strlen($sql) + 40 < $minChars) {
            $parts[] = '`col_'.$i.'`';
            ++$i;
            $sql = 'SELECT '.implode(', ', $parts).' FROM `huge_table` WHERE `id` = ?';
        }

        return $sql;
    }

    /**
     * Client tags that mirror where the failure happened (command / URL / message class).
     *
     * Real BeaconBundle listeners store this in `extra`; the issue UI also promotes those
     * as system tags. The suite sets explicit client tags so the client-tags UI is actionable.
     *
     * @param array{
     *     message: string,
     *     level: string,
     *     throwable: ?Throwable,
     *     extra: array<string, mixed>,
     *     fingerprint: list<string>,
     *     breadcrumbs: list<array{message: string, category: string, level: string}>,
     *     request: ?Request
     * } $spec
     *
     * @return array<string, string>
     */
    private function clientTagsForKind(string $kind, string $runToken, array $spec): array
    {
        $tags = [
            'probe_kind' => $kind,
            'probe_run' => $runToken,
            'source' => 'dogfood.suite',
        ];
        $rawMessengerClass = $spec['extra']['messenger']['message_class'] ?? null;
        $messengerClass = \is_string($rawMessengerClass) && '' !== $rawMessengerClass ? $rawMessengerClass : 'unknown';

        return match ($kind) {
            'console' => $tags + [
                'console.command' => 'app:beacon:test',
                'transaction' => 'cli://app:beacon:test',
            ],
            'http' => $tags + [
                'url' => (string) ($spec['extra']['request_uri'] ?? ''),
                'http.route' => (string) (($spec['extra']['http']['route'] ?? null) ?: 'project_issues_index'),
                'http.method' => (string) ($spec['extra']['request_method'] ?? 'GET'),
                'transaction' => (string) (($spec['extra']['http']['route'] ?? null) ?: 'project_issues_index'),
            ],
            'messenger' => $tags + [
                'messenger.message_class' => $messengerClass,
                'transaction' => 'messenger://'.$messengerClass,
            ],
            'exception' => $tags + [
                'transaction' => 'cli://app:beacon:test#exception',
            ],
            'breadcrumbs' => $tags + [
                'transaction' => 'cli://app:beacon:test#breadcrumbs',
            ],
            'db-sql', 'db-connection', 'long-content' => $tags + [
                'db.scenario' => (string) (($spec['extra']['db']['scenario'] ?? null) ?: $kind),
                'transaction' => 'cli://app:beacon:test#'.$kind,
            ],
            'message-info', 'message-error' => $tags + [
                'transaction' => 'cli://app:beacon:test#'.$kind,
            ],
            default => $tags,
        };
    }

    /**
     * @return array{ok: true, dsn: BeaconDsn}|array{ok: false, error: string}
     */
    private function parseDsn(): array
    {
        $dsnString = \trim($this->dsn);
        if ('' === $dsnString || str_starts_with($dsnString, '%')) {
            return [
                'ok' => false,
                'error' => 'Beacon DSN is empty. Set BEACON_DSN (or nowo_beacon.dsn) to a valid Symfony Beacon DSN.',
            ];
        }

        try {
            $dsn = $this->parser->parse($dsnString);
        } catch (InvalidBeaconDsnException $exception) {
            return [
                'ok' => false,
                'error' => 'Invalid Beacon DSN: '.$exception->getMessage(),
            ];
        }

        return ['ok' => true, 'dsn' => $dsn];
    }

    /**
     * @return array{
     *     origin: string,
     *     project_id: string,
     *     public_key: string,
     *     envelope_url: string,
     *     reporting_enabled: bool
     * }
     */
    private function sanitizeTarget(BeaconDsn $dsn): array
    {
        $publicKey = $dsn->getPublicKey();
        if (\strlen($publicKey) > 8) {
            $publicKey = \substr($publicKey, 0, 8).'…';
        }

        return [
            'origin' => $dsn->getOrigin(),
            'project_id' => $dsn->getProjectId(),
            'public_key' => $publicKey,
            'envelope_url' => $dsn->getEnvelopeUrl(),
            'reporting_enabled' => $this->reportingEnabled,
        ];
    }

    private function normalizeRunToken(?string $runToken): string
    {
        $token = null !== $runToken ? \trim($runToken) : '';
        if ('' === $token) {
            return \bin2hex(\random_bytes(4));
        }

        return \substr($token, 0, 32);
    }

    private function extractEventId(string $envelopeBody): ?string
    {
        $firstLine = \strtok($envelopeBody, "\n") ?: '';
        $decoded = \json_decode($firstLine, true);
        $eventId = \is_array($decoded) ? ($decoded['event_id'] ?? null) : null;

        return \is_string($eventId) ? $eventId : null;
    }
}
