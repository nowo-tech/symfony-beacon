<?php

declare(strict_types=1);

namespace App\Ops\Command;

use App\Ops\Service\BeaconDogfoodDiagnostics;
use App\Ops\Service\BeaconDogfoodProbeSuite;
use App\Ops\Service\BeaconDogfoodProbeSuiteReport;
use Nowo\BeaconBundle\Connection\BeaconConnectionTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dogfood wrapper around BeaconBundle {@see BeaconConnectionTester}: ACK probe + local push/issue hints.
 *
 * With `--suite`, posts several synthetic Envelopes to exercise issue detail panels.
 */
#[AsCommand(
    name: 'app:beacon:test',
    description: 'Probe BEACON_DSN ingest and warn when Web Push / new-issue alerts will not fire',
)]
final class BeaconTestCommand extends Command
{
    public function __construct(
        private readonly BeaconConnectionTester $tester,
        private readonly BeaconDogfoodDiagnostics $diagnostics,
        private readonly BeaconDogfoodProbeSuite $probeSuite,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'check-only',
                null,
                InputOption::VALUE_NONE,
                'Validate and display the DSN target without sending an Envelope',
            )
            ->addOption(
                'suite',
                null,
                InputOption::VALUE_NONE,
                'Send several synthetic events (message / exception / console / HTTP / messenger / breadcrumbs)',
            )
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'Message body for the single-probe test event (ignored with --suite)',
                'BeaconBundle connection test',
            )
            ->addOption(
                'wait',
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds to wait for Messenger to persist the event after ACK',
                '10',
            )
            ->addOption(
                'run-token',
                null,
                InputOption::VALUE_REQUIRED,
                'Stable token for suite fingerprints (default: random hex)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checkOnly = (bool) $input->getOption('check-only');
        $suite = (bool) $input->getOption('suite');
        $message = (string) $input->getOption('message');
        $waitSeconds = max(0, (int) $input->getOption('wait'));
        $runToken = $input->getOption('run-token');
        $runToken = \is_string($runToken) && '' !== trim($runToken) ? trim($runToken) : null;

        if ($suite) {
            return $this->executeSuite($io, $checkOnly, $waitSeconds, $runToken);
        }

        $result = $this->tester->test($checkOnly, $message);
        $this->writeTarget($io, $result->getTarget());

        if (null !== $result->getEventId()) {
            $io->writeln('Event id: <info>'.$result->getEventId().'</info>');
        }
        if (null !== $result->getHttpStatus()) {
            $io->writeln('HTTP status: <info>'.$result->getHttpStatus().'</info>');
        }

        if (!$result->isSuccess()) {
            $io->error($result->getMessage());

            return Command::FAILURE;
        }

        $io->success($result->getMessage());

        $projectRef = (string) ($result->getTarget()['project_id'] ?? '');
        if ('' === $projectRef) {
            $io->warning('No project id in DSN target — skipped local dogfood diagnostics.');

            return Command::SUCCESS;
        }

        return $this->writeDiagnostics(
            $io,
            $projectRef,
            $result->getEventId(),
            $checkOnly,
            $waitSeconds,
        );
    }

    private function executeSuite(SymfonyStyle $io, bool $checkOnly, int $waitSeconds, ?string $runToken): int
    {
        $report = $checkOnly
            ? $this->probeSuite->preview($runToken)
            : $this->probeSuite->run($runToken);

        $this->writeTarget($io, $report->target);
        $io->writeln('Suite run token: <info>'.$report->runToken.'</info>');

        if (null !== $report->errorMessage && [] === $report->target) {
            $io->error($report->errorMessage);

            return Command::FAILURE;
        }

        if ($checkOnly) {
            $io->success('DSN is valid. Suite would send '.\count($report->plannedKinds).' synthetic events (not sent).');
            $io->listing($report->plannedKinds);

            return $this->writeDiagnostics(
                $io,
                (string) ($report->target['project_id'] ?? ''),
                null,
                true,
                $waitSeconds,
            );
        }

        $rows = [];
        foreach ($report->cases as $case) {
            $rows[] = [
                $case->kind,
                null !== $case->httpStatus ? (string) $case->httpStatus : '—',
                $case->accepted ? 'yes' : 'no',
                $case->eventId ?? '—',
                $case->error ?? '',
            ];
        }
        $io->table(['Kind', 'HTTP', 'ACK', 'Event id', 'Error'], $rows);

        if (!$report->success) {
            $io->error($report->errorMessage ?? 'Suite failed.');

            return Command::FAILURE;
        }

        $io->success('Beacon ingest accepted all '.\count($report->cases).' suite envelopes.');
        $io->note('Open the Symfony Beacon project Issues list — filter by tag probe_run='.$report->runToken.' (or search the suite messages).');

        $projectRef = (string) ($report->target['project_id'] ?? '');
        if ('' === $projectRef) {
            $io->warning('No project id in DSN target — skipped local dogfood diagnostics.');

            return Command::SUCCESS;
        }

        $diagnosticEventId = $report->diagnosticEventId();
        if (null !== $diagnosticEventId) {
            $io->writeln('Diagnostics event id (console preferred): <info>'.$diagnosticEventId.'</info>');
        }

        return $this->writeDiagnostics(
            $io,
            $projectRef,
            $diagnosticEventId,
            false,
            $waitSeconds,
            $report,
        );
    }

    private function writeDiagnostics(
        SymfonyStyle $io,
        string $projectRef,
        ?string $eventId,
        bool $checkOnly,
        int $waitSeconds,
        ?BeaconDogfoodProbeSuiteReport $suiteReport = null,
    ): int {
        if ('' === $projectRef) {
            return Command::SUCCESS;
        }

        $report = $this->diagnostics->diagnose(
            $projectRef,
            $eventId,
            $checkOnly,
            $waitSeconds,
        );

        if ([] !== $report->notes) {
            $io->section('Dogfood diagnostics');
            foreach ($report->notes as $note) {
                $io->writeln(' • '.$note);
            }
        }

        if (null !== $suiteReport) {
            $io->writeln(' • Suite kinds ACKed: '.\count($suiteReport->cases).'/'.\count(BeaconDogfoodProbeSuite::KINDS));
        }

        if ([] !== $report->warnings) {
            $io->warning($report->warnings);

            return Command::SUCCESS;
        }

        if (!$checkOnly && $report->eventPersisted && !$report->priorIssueExisted && $report->pushSubscriptionCount > 0 && $report->vapidConfigured) {
            $io->note('New issue path + VAPID + at least one push subscription — if messenger-notify is running, eligible members may receive Web Push.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function writeTarget(SymfonyStyle $io, array $target): void
    {
        if ([] === $target) {
            return;
        }

        $io->definitionList(
            ['Origin' => $target['origin'] ?? ''],
            ['Project' => $target['project_id'] ?? ''],
            ['Public key' => $target['public_key'] ?? ''],
            ['Envelope URL' => $target['envelope_url'] ?? ''],
            ['Reporting enabled' => ($target['reporting_enabled'] ?? false) ? 'yes' : 'no'],
        );
    }
}
