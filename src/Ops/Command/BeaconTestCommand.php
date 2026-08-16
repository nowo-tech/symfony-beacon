<?php

declare(strict_types=1);

namespace App\Ops\Command;

use App\Ops\Service\BeaconDogfoodDiagnostics;
use Nowo\BeaconBundle\Connection\BeaconConnectionTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dogfood wrapper around BeaconBundle {@see BeaconConnectionTester}: ACK probe + local push/issue hints.
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
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'Message body for the test event',
                'BeaconBundle connection test',
            )
            ->addOption(
                'wait',
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds to wait for Messenger to persist the event after ACK',
                '10',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checkOnly = (bool) $input->getOption('check-only');
        $message = (string) $input->getOption('message');
        $waitSeconds = max(0, (int) $input->getOption('wait'));

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

        $report = $this->diagnostics->diagnose(
            $projectRef,
            $result->getEventId(),
            $checkOnly,
            $waitSeconds,
        );

        if ([] !== $report->notes) {
            $io->section('Dogfood diagnostics');
            foreach ($report->notes as $note) {
                $io->writeln(' • '.$note);
            }
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
