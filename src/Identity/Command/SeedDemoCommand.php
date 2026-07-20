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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds a demo admin user, sample project/API key, menus, N+1 samples, and analytics.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Seed a demo user, project, API key, breadcrumbs, menu, N+1 samples, and analytics')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly PerformanceDemoSeeder $performanceDemoSeeder,
        private readonly AnalyticsDemoSeeder $analyticsDemoSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Demo user email', 'admin@symfony-beacon.local')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Demo user password', 'admin123')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Public base URL for DSN', 'https://localhost:9444');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $baseUrl = (string) $input->getOption('base-url');

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

            $apiKey = ProjectApiKey::generate($project, 'Demo key');
            $project->addApiKey($apiKey);
            $this->projectRepository->save($project);
            $io->success('Created demo project');
        } else {
            $first = $project->getApiKeys()->first();
            $apiKey = $first instanceof ProjectApiKey ? $first : null;
            $io->note('Demo project already exists');
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
            $io->writeln('DSN: '.$apiKey->buildDsn($baseUrl));
            $io->writeln('Public key: '.$apiKey->getPublicKey());
            $io->writeln(\sprintf('Login: %s / %s', $email, $password));
        }

        return Command::SUCCESS;
    }
}
