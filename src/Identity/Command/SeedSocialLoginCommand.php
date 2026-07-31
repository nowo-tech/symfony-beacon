<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Service\SocialLoginCredentialSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sync AuthKit social OAuth credentials from AUTH_KIT_SOCIAL_* env vars into the database.
 */
#[AsCommand(
    name: 'app:seed-social-login',
    description: 'Upsert AuthKit social login credentials from AUTH_KIT_SOCIAL_* environment variables',
)]
final class SeedSocialLoginCommand extends Command
{
    public function __construct(
        private readonly SocialLoginCredentialSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = $this->seeder->seedFromEnv();

        if ([] === $updated) {
            $io->warning('No AUTH_KIT_SOCIAL_{PROVIDER}_CLIENT_ID/SECRET pairs found; nothing changed.');
            $io->note('Example: AUTH_KIT_SOCIAL_GOOGLE_CLIENT_ID=… AUTH_KIT_SOCIAL_GOOGLE_CLIENT_SECRET=…');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Upserted social credentials for: %s', implode(', ', $updated)));

        return Command::SUCCESS;
    }
}
