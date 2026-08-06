<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Entity\ProjectApiKey;
use App\Setup\Demo\BreadcrumbDemoSeeder;
use App\Setup\Demo\CookieConsentDemoSeeder;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use LogicException;
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
     * Stable public key for new dogfood projects (Envelope auth). Existing projects keep their key.
     */
    public const string DEMO_PUBLIC_KEY = 'd0e1b2eac0ffeedem0beac0nkey00001';

    /**
     * Stable secret for new dogfood projects (local dogfooding / reproducible BEACON_DSN).
     * Existing projects keep their secret.
     */
    public const string DEMO_SECRET_KEY = 'd0e1b2eac0ffeedem0beac0nsec00001';

    /** Canonical local dogfood project slug (UI + Envelope project id path still uses numeric id). */
    public const string DEMO_PROJECT_SLUG = 'symfony-beacon';

    /** Pre-rename slug; resolved then upgraded to {@see DEMO_PROJECT_SLUG}. */
    public const string LEGACY_DEMO_PROJECT_SLUG = 'demo';

    public const string DEMO_PROJECT_NAME = 'Symfony Beacon';

    public const string DEMO_PROJECT_DESCRIPTION = 'Primary project for this self-hosted Beacon instance — local Envelope ingest, dogfooding, and sample telemetry.';

    public const string DEMO_API_KEY_NAME = 'Symfony Beacon key';

    /**
     * Env file consumed by BeaconBundle demo `make sync-beacon`.
     * Written under the project root (not var/) so the host bind-mount sees it — Compose shadows /app/var.
     */
    public const string CLIENT_ENV_FILENAME = '.demo-client.env';

    /**
     * Loopback ingest base URL for this container (dogfooding via nowo-tech/beacon-bundle).
     */
    public const string SELF_INGEST_BASE_URL = 'http://127.0.0.1';

    public function __construct(
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly CookieConsentDemoSeeder $cookieConsentDemoSeeder,
        private readonly InstanceSettingsRepository $instanceSettingsRepository,
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
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Browser / UI base URL for DSN display', 'https://localhost:9447')
            ->addOption('ingest-base-url', null, InputOption::VALUE_REQUIRED, 'Docker client ingest base URL for BEACON_DSN', 'http://host.docker.internal:9084')
            ->addOption('write-client-env', null, InputOption::VALUE_OPTIONAL, 'Path for demo-client.env (empty string skips write)')
            ->addOption('with-platform', null, InputOption::VALUE_NONE, 'Also run platform menu/breadcrumb/cookie-consent seed')
            ->addOption('skip-demo-user', null, InputOption::VALUE_NONE, 'Do not create the demo admin user; use existing ROLE_ADMIN accounts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $baseUrl = (string) $input->getOption('base-url');
        $ingestBaseUrl = (string) $input->getOption('ingest-base-url');
        $skipDemoUser = (bool) $input->getOption('skip-demo-user');

        if ((bool) $input->getOption('with-platform')) {
            if ($this->breadcrumbDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated default breadcrumb collection');
            }
            if ($this->dashboardMenuDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated navigation menus');
            }
            if ($this->cookieConsentDemoSeeder->seedIfEmpty()) {
                $io->success('Seeded / updated cookie consent profile and inventory');
            }
        }

        try {
            $result = $this->demoIdentitySeeder->seed($email, $password, !$skipDemoUser);
        } catch (LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($skipDemoUser) {
            $io->note('Skipped demo user creation (--skip-demo-user); using existing accounts');
        } elseif ($result['user_created']) {
            $io->success(\sprintf('Created user %s', $email));
        } else {
            $io->note(\sprintf('User %s already exists', $email));
        }
        if ($result['project_created']) {
            $io->success('Created Symfony Beacon project / API key');
        } else {
            $io->note('Symfony Beacon project already exists');
        }
        if ($result['admins_granted'] > 0) {
            $io->success(\sprintf(
                'Granted Symfony Beacon project access to %d instance admin(s)',
                $result['admins_granted'],
            ));
        } else {
            $io->note('All instance admins already have Symfony Beacon project access');
        }

        $settings = $this->instanceSettingsRepository->getOrCreate();
        if (!$settings->isSetupCompleted()) {
            $settings->markSetupCompleted();
            $this->instanceSettingsRepository->save($settings);
            $doneMarker = $this->projectDir.'/var/site-backup/setup.done';
            if (!is_dir(\dirname($doneMarker))) {
                mkdir(\dirname($doneMarker), 0775, true);
            }
            if (!is_file($doneMarker)) {
                touch($doneMarker);
            }
        }

        $apiKey = $result['api_key'];
        $owner = $result['user'];
        $uiDsn = $apiKey->buildDsn($baseUrl);
        $clientDsn = $apiKey->buildDsn($ingestBaseUrl);
        $selfDsn = $apiKey->buildDsn(self::SELF_INGEST_BASE_URL);
        $io->writeln('UI DSN: '.$uiDsn);
        $io->writeln('Client DSN (Docker / BeaconBundle demo): '.$clientDsn);
        $io->writeln('Self DSN (server dogfood / 127.0.0.1): '.$selfDsn);
        $io->writeln('Public key: '.$apiKey->getPublicKey());
        if ($skipDemoUser) {
            $io->writeln(\sprintf('Sign in with an existing ROLE_ADMIN (e.g. %s)', $owner->getEmail()));
        } else {
            $io->writeln(\sprintf('Login: %s / %s', $email, $password));
        }
        $io->note('Sample telemetry: bin/console app:seed-sample --size=dev');

        $writeOpt = $input->getOption('write-client-env');
        if (!\is_string($writeOpt) || '' !== $writeOpt) {
            $path = \is_string($writeOpt) ? $writeOpt : $this->projectDir.'/'.self::CLIENT_ENV_FILENAME;
            $loginEmail = $skipDemoUser ? $owner->getEmail() : $email;
            $loginPassword = $skipDemoUser ? '' : $password;
            $this->writeClientEnv($path, $clientDsn, $uiDsn, $apiKey, $loginEmail, $loginPassword);
            $io->success(\sprintf('Wrote %s (BeaconBundle demo: make sync-beacon)', $path));
        }

        if ($this->writeServerBeaconDsnIfEmpty($selfDsn)) {
            $io->success(\sprintf('Set BEACON_DSN in .env for server dogfooding (%s)', $selfDsn));
        } else {
            $io->note('BEACON_DSN already set in .env (left unchanged)');
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
# Prefer Client DSN (HTTP :9084) from Docker FrankenPHP demos.
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

    /**
     * Write loopback BEACON_DSN into project `.env` when the variable is missing or empty.
     *
     * Does not overwrite an operator-chosen DSN.
     */
    private function writeServerBeaconDsnIfEmpty(string $selfDsn): bool
    {
        $path = $this->projectDir.'/.env';
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return false;
        }

        if (preg_match('/^BEACON_DSN=(.*)$/m', $contents, $matches)) {
            $current = trim($matches[1], " \t\"'");
            if ('' !== $current) {
                return false;
            }
            $updated = preg_replace('/^BEACON_DSN=.*$/m', 'BEACON_DSN='.$selfDsn, $contents, 1);
        } else {
            $suffix = str_ends_with($contents, "\n") ? '' : "\n";
            $updated = $contents.$suffix."\n# Server dogfooding (nowo-tech/beacon-bundle) — empty disables reporting.\nBEACON_DSN=".$selfDsn."\n";
        }

        if (!\is_string($updated) || $updated === $contents) {
            return false;
        }

        if (false === file_put_contents($path, $updated)) {
            throw new RuntimeException(\sprintf('Unable to update BEACON_DSN in "%s".', $path));
        }

        return true;
    }
}
