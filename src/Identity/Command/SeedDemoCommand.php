<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Entity\ProjectApiKey;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds a demo admin user, sample project/API key, and optional client env for BeaconBundle.
 *
 * Navigation catalogs: `app:seed-platform`. Sample telemetry: `app:seed-sample`.
 */
#[AsCommand(
    name: 'app:seed-demo',
    description: 'Seed demo user + project + API key (+ optional .demo-client.env)',
)]
final class SeedDemoCommand extends Command
{
    /**
     * Stable public key for new demo projects (Envelope auth). Existing projects keep their key.
     */
    public const string DEMO_PUBLIC_KEY = 'd0e1b2eac0ffeedem0beac0nkey00001';

    /**
     * Env file consumed by BeaconBundle demo `make sync-beacon`.
     * Written under the project root (not var/) so the host bind-mount sees it — Compose shadows /app/var.
     */
    public const string CLIENT_ENV_FILENAME = '.demo-client.env';

    public function __construct(
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Demo user email', 'admin@symfony-beacon.local')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Demo user password', 'admin123')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Browser / UI base URL for DSN display', 'https://localhost:9444')
            ->addOption('ingest-base-url', null, InputOption::VALUE_REQUIRED, 'Docker client ingest base URL for BEACON_DSN', 'http://host.docker.internal:9081')
            ->addOption('write-client-env', null, InputOption::VALUE_OPTIONAL, 'Path for demo-client.env (empty string skips write)')
            ->addOption('with-platform', null, InputOption::VALUE_NONE, 'Also run platform menu/breadcrumb seed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $baseUrl = (string) $input->getOption('base-url');
        $ingestBaseUrl = (string) $input->getOption('ingest-base-url');

        if ((bool) $input->getOption('with-platform')) {
            if ($this->breadcrumbDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated default breadcrumb collection');
            }
            if ($this->dashboardMenuDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated navigation menus');
            }
        }

        $result = $this->demoIdentitySeeder->seed($email, $password);
        if ($result['user_created']) {
            $io->success(\sprintf('Created user %s', $email));
        } else {
            $io->note(\sprintf('User %s already exists', $email));
        }
        if ($result['project_created']) {
            $io->success('Created demo project / API key');
        } else {
            $io->note('Demo project already exists');
        }

        $apiKey = $result['api_key'];
        $uiDsn = $apiKey->buildDsn($baseUrl);
        $clientDsn = $apiKey->buildDsn($ingestBaseUrl);
        $io->writeln('UI DSN: '.$uiDsn);
        $io->writeln('Client DSN (Docker / BeaconBundle demo): '.$clientDsn);
        $io->writeln('Public key: '.$apiKey->getPublicKey());
        $io->writeln(\sprintf('Login: %s / %s', $email, $password));
        $io->note('Sample telemetry: bin/console app:seed-sample --size=dev');

        $writeOpt = $input->getOption('write-client-env');
        if (!\is_string($writeOpt) || '' !== $writeOpt) {
            $path = \is_string($writeOpt) ? $writeOpt : $this->projectDir.'/'.self::CLIENT_ENV_FILENAME;
            $this->writeClientEnv($path, $clientDsn, $uiDsn, $apiKey, $email, $password);
            $io->success(\sprintf('Wrote %s (BeaconBundle demo: make sync-beacon)', $path));
        }

        return Command::SUCCESS;
    }

    private function writeClientEnv(
        string $path,
        string $clientDsn,
        string $uiDsn,
        ProjectApiKey $apiKey,
        string $email,
        string $password,
    ): void {
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf('Unable to create directory "%s".', $dir));
        }

        $projectId = $apiKey->getProject()?->getId() ?? 0;
        $contents = <<<ENV
# Generated by app:seed-demo — consumed by BeaconBundle demo `make sync-beacon`.
# Prefer Client DSN (HTTP :9081) from Docker FrankenPHP demos.
BEACON_DSN={$clientDsn}
BEACON_UI_DSN={$uiDsn}
BEACON_PROJECT_ID={$projectId}
BEACON_PUBLIC_KEY={$apiKey->getPublicKey()}
BEACON_LOGIN_EMAIL={$email}
BEACON_LOGIN_PASSWORD={$password}

ENV;

        if (false === file_put_contents($path, $contents)) {
            throw new RuntimeException(\sprintf('Unable to write "%s".', $path));
        }
    }
}
