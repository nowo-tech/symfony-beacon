<?php

declare(strict_types=1);

namespace App\Notifications\Command;

use App\Notifications\Service\NotificationDigestFlusher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cron/scheduler entry: flush quiet-hours buffers as digests or individual deliveries.
 */
#[AsCommand(
    name: 'app:notifications:flush-digests',
    description: 'Flush buffered quiet-hours notifications (digest or individual)',
)]
final class FlushNotificationDigestsCommand extends Command
{
    public function __construct(
        private readonly NotificationDigestFlusher $flusher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Flush even while quiet hours are still active');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $result = $this->flusher->flush($force);

        $io->success(\sprintf(
            'Flushed %d destination(s), queued %d message(s); skipped %d still in quiet hours.',
            $result['destinations'],
            $result['messages'],
            $result['skipped_quiet'],
        ));

        return Command::SUCCESS;
    }
}
