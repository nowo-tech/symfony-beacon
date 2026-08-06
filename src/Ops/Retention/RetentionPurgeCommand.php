<?php

declare(strict_types=1);

namespace App\Ops\Retention;

use App\Shared\Settings\Service\InstanceOpsDefaults;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console entry point for telemetry retention purge (cron / scheduler).
 */
#[AsCommand(name: 'app:retention:purge', description: 'Purge telemetry older than retention policy / over max events')]
final class RetentionPurgeCommand extends Command
{
    public function __construct(
        private readonly RetentionPurger $retentionPurger,
        private readonly InstanceOpsDefaults $opsDefaults,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print configured limits without purging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $retentionDays = $this->opsDefaults->retentionDays();
        $maxEventsPerProject = $this->opsDefaults->retentionMaxEvents();
        $io->writeln(\sprintf(
            'Retention policy: days=%d, max_events_per_project=%d (0 = disabled)',
            $retentionDays,
            $maxEventsPerProject,
        ));

        if ($input->getOption('dry-run')) {
            $io->success('Dry-run only; no rows deleted.');

            return Command::SUCCESS;
        }

        if ($retentionDays < 1 && $maxEventsPerProject < 1) {
            $io->warning('Both instance retention defaults are disabled; nothing to do.');

            return Command::SUCCESS;
        }

        $totals = $this->retentionPurger->purge();
        $io->success(\sprintf(
            'Purged across %d project(s): %d events, %d issues, %d transactions, %d daily stats.',
            $totals['projects'],
            $totals['events'],
            $totals['issues'],
            $totals['transactions'],
            $totals['stats'],
        ));

        return Command::SUCCESS;
    }
}
