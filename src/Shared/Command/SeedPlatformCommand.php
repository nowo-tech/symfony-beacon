<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\CookieConsent\CookieConsentDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Idempotent product catalogs (menus + breadcrumbs + cookie consent) for install and upgrade.
 *
 * Does not create users, projects, or sample telemetry.
 */
#[AsCommand(
    name: 'app:seed-platform',
    description: 'Seed/upsert platform navigation and cookie consent — safe for production upgrades',
)]
final class SeedPlatformCommand extends Command
{
    public function __construct(
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly CookieConsentDemoSeeder $cookieConsentDemoSeeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($this->breadcrumbDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated default breadcrumb collection');
            } else {
                $io->note('Default breadcrumb collection already up to date');
            }

            if ($this->dashboardMenuDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated navigation menus');
            } else {
                $io->note('Navigation menus already up to date');
            }

            if ($this->cookieConsentDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated cookie consent profile and inventory');
            } else {
                $io->note('Cookie consent profile already up to date');
            }
        } catch (Throwable $e) {
            $io->error('Platform seed failed. Run doctrine:migrations:migrate first, then retry.');
            $io->writeln($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
