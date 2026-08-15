<?php

declare(strict_types=1);

namespace App\Shared\Sms\Command;

use App\Shared\Sms\SmsOutboundMessage;
use App\Shared\Sms\SmsSenderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sms:send',
    description: 'Send a test SMS via the configured provider (SMS Bridge, …)',
)]
final class SmsSendCommand extends Command
{
    public function __construct(
        private readonly SmsSenderInterface $smsSender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Destination E.164 (+34600111222)')
            ->addArgument('body', InputArgument::REQUIRED, 'Message body')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Override From E.164')
            ->addOption('device-id', null, InputOption::VALUE_REQUIRED, 'SMS Bridge device UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note(\sprintf('Provider: %s (configured=%s)', $this->smsSender->getProviderId(), $this->smsSender->isConfigured() ? 'yes' : 'no'));

        if (!$this->smsSender->isConfigured()) {
            $io->error('SMS provider is not configured. Set SMS_PROVIDER=sms_bridge and SMS_BRIDGE_* in .env.local.');

            return Command::FAILURE;
        }

        $from = $input->getOption('from');
        $deviceId = $input->getOption('device-id');
        $result = $this->smsSender->send(new SmsOutboundMessage(
            (string) $input->getArgument('to'),
            (string) $input->getArgument('body'),
            \is_string($from) && '' !== $from ? $from : null,
            \is_string($deviceId) && '' !== $deviceId ? $deviceId : null,
        ));

        $io->success(\sprintf(
            'Enqueued via %s — id=%s status=%s',
            $result->providerId,
            $result->providerMessageId,
            $result->status,
        ));

        return Command::SUCCESS;
    }
}
