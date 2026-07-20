<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Analytics\Service\AnalyticsDemoSeeder;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectRepository;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\ProjectRole;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds a demo admin user, sample project/API key, menus, N+1 samples, and analytics.
 *
 * Writes {@see self::CLIENT_ENV_FILENAME} so the BeaconBundle FrankenPHP demo can sync BEACON_DSN.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Seed a demo user, project, API key, breadcrumbs, menu, N+1 samples, and analytics')]
final class SeedDemoCommand extends Command
{
    /**
     * Stable public key for new demo projects (Envelope auth). Existing projects keep their key.
     */
    public const DEMO_PUBLIC_KEY = 'd0e1b2eac0ffeedem0beac0nkey00001';

    /**
     * Env file consumed by BeaconBundle demo `make sync-beacon`.
     * Written under the project root (not var/) so the host bind-mount sees it — Compose shadows /app/var.
     */
    public const CLIENT_ENV_FILENAME = '.demo-client.env';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly PerformanceDemoSeeder $performanceDemoSeeder,
        private readonly AnalyticsDemoSeeder $analyticsDemoSeeder,
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
            ->addOption('write-client-env', null, InputOption::VALUE_OPTIONAL, 'Path for demo-client.env (empty string skips write)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $baseUrl = (string) $input->getOption('base-url');
        $ingestBaseUrl = (string) $input->getOption('ingest-base-url');

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $user->setDisplayName('Demo Admin');
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $this->userRepository->save($user);
            $io->success(\sprintf('Created user %s', $email));
        } else {
            $io->note(\sprintf('User %s already exists', $email));
        }

        $project = $this->projectRepository->findOneBy(['slug' => 'demo']);
        $apiKey = null;
        if (null === $project) {
            $project = new Project();
            $project->setName('Demo');
            $project->setSlug('demo');
            $project->setDescription('Demo project for local ingest');

            $membership = new ProjectMembership();
            $membership->setUser($user);
            $membership->setRole(ProjectRole::Owner);
            $project->addMembership($membership);

            $apiKey = ProjectApiKey::generate($project, 'Demo key', self::DEMO_PUBLIC_KEY);
            $project->addApiKey($apiKey);
            $this->projectRepository->save($project);
            $io->success('Created demo project');
        } else {
            $first = $project->getApiKeys()->first();
            $apiKey = $first instanceof ProjectApiKey ? $first : null;
            if (!$apiKey instanceof ProjectApiKey) {
                $apiKey = ProjectApiKey::generate($project, 'Demo key', self::DEMO_PUBLIC_KEY);
                $project->addApiKey($apiKey);
                $this->projectRepository->save($project);
                $io->success('Created demo API key on existing project');
            } else {
                $io->note('Demo project already exists');
            }
        }

        if ($this->breadcrumbDemoSeeder->seedIfEmpty()) {
            $io->success('Seeded default breadcrumb collection');
        } else {
            $io->note('Default breadcrumb collection already exists');
        }

        if ($this->dashboardMenuDemoSeeder->seedIfEmpty()) {
            $io->success('Seeded main navigation menu');
        } else {
            $io->note('Main navigation menu already exists');
        }

        if ($project instanceof Project && $this->performanceDemoSeeder->seedIfEmpty($project)) {
            $io->success('Seeded performance samples (including N+1). Open /projects/{id}/performance?nplus1=1');
        } else {
            $io->note('Performance N+1 demo samples already exist');
        }

        if ($project instanceof Project && $this->analyticsDemoSeeder->seedIfEmpty($project)) {
            $io->success('Seeded analytics daily stats (14-day window). Open /projects/{id}/analytics');
        } else {
            $io->note('Analytics daily stats already cover the demo window');
        }

        if ($apiKey instanceof ProjectApiKey) {
            $uiDsn = $apiKey->buildDsn($baseUrl);
            $clientDsn = $apiKey->buildDsn($ingestBaseUrl);
            $io->writeln('UI DSN: '.$uiDsn);
            $io->writeln('Client DSN (Docker / BeaconBundle demo): '.$clientDsn);
            $io->writeln('Public key: '.$apiKey->getPublicKey());
            $io->writeln(\sprintf('Login: %s / %s', $email, $password));

            $writeOpt = $input->getOption('write-client-env');
            if ('' !== $writeOpt) {
                $path = \is_string($writeOpt) && '' !== $writeOpt
                    ? $writeOpt
                    : $this->projectDir.'/'.self::CLIENT_ENV_FILENAME;
                $this->writeClientEnv($path, $clientDsn, $uiDsn, $apiKey, $email, $password);
                $io->success(\sprintf('Wrote %s (BeaconBundle demo: make sync-beacon)', $path));
            }
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
