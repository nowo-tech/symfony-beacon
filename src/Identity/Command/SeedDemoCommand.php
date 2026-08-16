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
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
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
            ->addOption('skip-demo-user', null, InputOption::VALUE_NONE, 'Do not create admin@…; dogfood with existing ROLE_ADMIN accounts (earliest registered preferred)')
            ->addOption('sync-server-dsn', null, InputOption::VALUE_NONE, 'Update .env BEACON_DSN to the loopback self DSN even when already set (make dogfood)')
            ->addOption('allow-non-local', null, InputOption::VALUE_NONE, 'Allow running outside dev/test (never uses stable demo API keys)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $allowNonLocal = (bool) $input->getOption('allow-non-local');
        $isLocal = \in_array($this->environment, ['dev', 'test'], true);

        if (!$isLocal && !$allowNonLocal) {
            $io->error(\sprintf(
                'app:seed-demo is blocked in "%s". Use only in local development (dev/test), or pass --allow-non-local (random API keys; never stable DEMO_* secrets).',
                $this->environment,
            ));

            return Command::FAILURE;
        }

        if (!$isLocal && $allowNonLocal) {
            $io->warning('Running seed-demo outside local development: stable DEMO_* API keys are disabled; credentials are generated randomly.');
        }

        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $baseUrl = (string) $input->getOption('base-url');
        $ingestBaseUrl = (string) $input->getOption('ingest-base-url');
        $skipDemoUser = (bool) $input->getOption('skip-demo-user');
        $syncServerDsn = (bool) $input->getOption('sync-server-dsn');
        $useStableDemoKeys = $isLocal;
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
            $result = $this->demoIdentitySeeder->seed($email, $password, !$skipDemoUser, $useStableDemoKeys);
        } catch (LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($skipDemoUser) {
            $io->note('Skipped demo user creation (--skip-demo-user); granting existing ROLE_ADMIN accounts');
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
        $demoSecret = self::DEMO_SECRET_KEY;
        $uiDsn = $apiKey->buildDsn($baseUrl, $demoSecret);
        $clientDsn = $apiKey->buildDsn($ingestBaseUrl, $demoSecret);
        $selfDsn = $apiKey->buildDsn(self::SELF_INGEST_BASE_URL, $demoSecret);
        $io->writeln('UI DSN: '.$uiDsn);
        $io->writeln('Client DSN (Docker / BeaconBundle demo): '.$clientDsn);
        $io->writeln('Self DSN (server dogfood / 127.0.0.1): '.$selfDsn);
        $io->writeln('Public key: '.$apiKey->getPublicKey());
        if ($skipDemoUser) {
            $io->writeln(\sprintf(
                'Sign in with an existing ROLE_ADMIN (first registered: %s). Password is not written to .demo-client.env.',
                $owner->getEmail(),
            ));
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

        $dsnWrite = $this->writeServerBeaconDsn($selfDsn, $syncServerDsn);
        match ($dsnWrite) {
            'written' => $io->success(\sprintf('Set BEACON_DSN for server dogfooding (%s)', $selfDsn)),
            'unchanged' => $io->note('BEACON_DSN already matches the self DSN'),
            'skipped' => $io->note('BEACON_DSN already set (left unchanged; use --sync-server-dsn to re-wire)'),
        };

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

        // Contains DSN + login credentials — never leave world-writable on shared hosts/WSL.
        if (!chmod($path, 0600) && is_file($path)) {
            // Best-effort on filesystems that ignore mode bits; seed still succeeded.
        }
    }

    /**
     * Write loopback BEACON_DSN into `.env.local` (Compose env_file) or `.env` as fallback.
     *
     * By default only fills a missing/empty value (preserves operator-chosen DSNs).
     * With $force (make dogfood / --sync-server-dsn), always re-wires to the current self DSN
     * so a recreated Symfony Beacon project does not leave a stale project UUID in env files.
     *
     * @return 'written'|'unchanged'|'skipped' written = file updated; unchanged = already correct;
     *                                         skipped = non-empty value left alone (no force) or no file
     */
    private function writeServerBeaconDsn(string $selfDsn, bool $force = false): string
    {
        $path = $this->resolveServerEnvPath();
        if (null === $path) {
            return 'skipped';
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return 'skipped';
        }

        if (preg_match('/^BEACON_DSN=(.*)$/m', $contents, $matches)) {
            $current = trim($matches[1], " \t\"'");
            if ($current === $selfDsn) {
                return 'unchanged';
            }
            if ('' !== $current && !$force) {
                return 'skipped';
            }
            $updated = preg_replace('/^BEACON_DSN=.*$/m', 'BEACON_DSN='.$selfDsn, $contents, 1);
        } else {
            $suffix = str_ends_with($contents, "\n") ? '' : "\n";
            $updated = $contents.$suffix."\n# Server dogfooding (nowo-tech/beacon-bundle) — empty disables reporting.\nBEACON_DSN=".$selfDsn."\n";
        }

        if (!\is_string($updated) || $updated === $contents) {
            return 'unchanged';
        }

        if (false === file_put_contents($path, $updated)) {
            throw new RuntimeException(\sprintf('Unable to update BEACON_DSN in "%s".', $path));
        }

        return 'written';
    }

    /**
     * Prefer `.env.local` (operator Compose env_file) over `.env`.
     */
    private function resolveServerEnvPath(): ?string
    {
        foreach (['.env.local', '.env'] as $name) {
            $path = $this->projectDir.'/'.$name;
            if (is_file($path) && is_readable($path) && is_writable($path)) {
                return $path;
            }
        }

        return null;
    }
}
