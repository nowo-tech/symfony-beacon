<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Service\SampleDataService;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Optional QA/load sample telemetry for a project (default slug=demo).
 */
#[AsCommand(
    name: 'app:seed-sample',
    description: 'Seed or purge sample telemetry (sizes: dev, load, huge)',
)]
final class SeedSampleCommand extends Command
{
    public function __construct(
        private readonly SampleDataService $sampleDataService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'dev|load|huge', 'dev')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'Target project slug', 'demo')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Delete issues/events/perf/stats for the project (keep project + keys)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Required for size=huge');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $profile = (string) $input->getOption('size');
        $slug = (string) $input->getOption('project');
        $purge = (bool) $input->getOption('purge');
        $force = (bool) $input->getOption('force');

        try {
            $project = $this->sampleDataService->resolveProject($slug);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($purge) {
            $removed = $this->sampleDataService->purge($project);
            $io->success(\sprintf(
                'Purged sample telemetry for project "%s" (issues≈%d, transactions=%d, daily stats=%d)',
                $slug,
                $removed['issues'],
                $removed['transactions'],
                $removed['stats'],
            ));

            return Command::SUCCESS;
        }

        if ('huge' === $profile && !$force) {
            $io->error('Size "huge" requires --force (large disk/time cost).');

            return Command::FAILURE;
        }

        if (\in_array($profile, ['load', 'huge'], true)) {
            $io->warning(\sprintf('Size "%s" may take several minutes and use significant disk.', $profile));
        }

        try {
            $result = $this->sampleDataService->seed($project, $profile);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Sample size "%s" on project "%s": +%d issues, +%d events (analytics %s, performance %s, Mercure %s)',
            $profile,
            $slug,
            $result['issues'],
            $result['events'],
            $result['analytics'] ? 'updated' : 'unchanged',
            $result['performance'] ? 'seeded' : 'already present',
            $result['mercure'] ? 'enabled/configured' : 'already configured',
        ));
        $io->note('Mercure live alerts use Administration → Mercure (enabled by sample seed). See docs/MERCURE.md.');

        return Command::SUCCESS;
    }
}
