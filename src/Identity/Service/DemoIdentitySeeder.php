<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Command\SeedDemoCommand;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectRepository;
use App\Shared\ProjectRole;
use DateTime;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates the local demo admin + demo project (shared by CLI and setup wizard).
 */
final readonly class DemoIdentitySeeder
{
    public function __construct(
        private UserRepository $userRepository,
        private ProjectRepository $projectRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @return array{user_created: bool, project_created: bool, project: Project, api_key: ProjectApiKey, user: User}
     */
    public function seed(
        string $email = 'admin@symfony-beacon.local',
        string $password = 'admin123',
    ): array {
        $userCreated = false;
        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $user->setDisplayName('Demo Admin');
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setPasswordChangedAt(new DateTime());
            $this->userRepository->save($user);
            $userCreated = true;
        }

        $projectCreated = false;
        $project = $this->projectRepository->findOneBy(['slug' => 'demo']);
        $apiKey = null;
        if (!$project instanceof Project) {
            $project = new Project();
            $project->setName('Demo');
            $project->setSlug('demo');
            $project->setDescription('Demo project for local ingest');

            $membership = new ProjectMembership();
            $membership->setUser($user);
            $membership->setRole(ProjectRole::Owner);
            $project->addMembership($membership);

            $apiKey = ProjectApiKey::generate($project, 'Demo key', SeedDemoCommand::DEMO_PUBLIC_KEY);
            $project->addApiKey($apiKey);
            $this->projectRepository->save($project);
            $projectCreated = true;
        } else {
            $first = $project->getApiKeys()->first();
            $apiKey = $first instanceof ProjectApiKey ? $first : null;
            if (!$apiKey instanceof ProjectApiKey) {
                $apiKey = ProjectApiKey::generate($project, 'Demo key', SeedDemoCommand::DEMO_PUBLIC_KEY);
                $project->addApiKey($apiKey);
                $this->projectRepository->save($project);
                $projectCreated = true;
            }
        }

        return [
            'user_created' => $userCreated,
            'project_created' => $projectCreated,
            'project' => $project,
            'api_key' => $apiKey,
            'user' => $user,
        ];
    }

    /**
     * Ensure the demo project exists for sample telemetry.
     *
     * When an owner is provided (or any admin already exists), the project is
     * attached to that user. Otherwise falls back to {@see seed()} (creates the
     * local demo admin) so sample load can run before AuthKit registration.
     *
     * @return array{user_created: bool, project_created: bool, project: Project}
     */
    public function ensureDemoProject(?User $owner = null): array
    {
        $existing = $this->projectRepository->findOneBy(['slug' => 'demo']);
        if ($existing instanceof Project) {
            return [
                'user_created' => false,
                'project_created' => false,
                'project' => $existing,
            ];
        }

        if (!$owner instanceof User) {
            $owner = $this->userRepository->findOneBy([]);
        }

        if (!$owner instanceof User) {
            $result = $this->seed();

            return [
                'user_created' => $result['user_created'],
                'project_created' => $result['project_created'],
                'project' => $result['project'],
            ];
        }

        $project = new Project();
        $project->setName('Demo');
        $project->setSlug('demo');
        $project->setDescription('Demo project for local ingest');

        $membership = new ProjectMembership();
        $membership->setUser($owner);
        $membership->setRole(ProjectRole::Owner);
        $project->addMembership($membership);

        $apiKey = ProjectApiKey::generate($project, 'Demo key', SeedDemoCommand::DEMO_PUBLIC_KEY);
        $project->addApiKey($apiKey);
        $this->projectRepository->save($project);

        return [
            'user_created' => false,
            'project_created' => true,
            'project' => $project,
        ];
    }
}
