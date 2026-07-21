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
}
